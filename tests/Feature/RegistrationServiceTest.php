<?php

use App\Exceptions\HostCannotJoinException;
use App\Exceptions\TripFullException;
use App\Models\MagicLink;
use App\Models\TripDeparture;
use App\Models\User;
use App\Services\RegistrationService;
use App\Support\AccountOutcome;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->svc = app(RegistrationService::class);
    $this->departure = TripDeparture::factory()->create(['capacity' => 10]);
});

function regData(array $overrides = []): array
{
    return array_merge([
        'party_size' => 1,
        'contact' => [
            'first_name' => 'Ada', 'middle_name' => null, 'last_name' => 'Reyes',
            'email' => 'ada@example.com', 'phone' => '09171234567',
        ],
        'booker_is_traveling' => true,
        'participants' => [
            ['first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02', 'gender' => 'female'],
        ],
        'create_account' => false,
        'actor' => null,
    ], $overrides);
}

it('registers a guest for one seat', function () {
    $result = $this->svc->register($this->departure, regData());
    $reservation = $result->reservation;

    expect($result->outcome)->toBe(AccountOutcome::Guest)
        ->and($reservation->joiner_id)->toBeNull()
        ->and($reservation->contact_email)->toBe('ada@example.com')
        ->and($reservation->participants)->toHaveCount(1)
        ->and($reservation->participants->first()->is_booker)->toBeTrue();
});

it('creates one participant row per seat even when details are deferred', function () {
    $result = $this->svc->register($this->departure, regData([
        'party_size' => 3,
        'participants' => [
            ['first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02', 'gender' => 'female',
                'emergency_contact_name' => 'Rosa Reyes', 'emergency_contact_phone' => '09171112222'],
            ['first_name' => 'Ben', 'last_name' => 'Cruz', 'birthday' => '1995-01-10', 'gender' => 'male',
                'emergency_contact_name' => 'Lito Cruz', 'emergency_contact_phone' => '09171113333'],
            [],   // "I'll add this later"
        ],
    ]));

    expect($result->reservation->participants)->toHaveCount(3)
        ->and($result->reservation->incompleteParticipants())->toHaveCount(1)
        ->and($result->reservation->party_size)->toBe(3);
});

it('lets a booker pay without taking a seat', function () {
    $result = $this->svc->register($this->departure, regData([
        'party_size' => 2,
        'booker_is_traveling' => false,
        'participants' => [
            ['first_name' => 'Ben', 'last_name' => 'Cruz', 'birthday' => '1995-01-10', 'gender' => 'male'],
            ['first_name' => 'Cara', 'last_name' => 'Lim', 'birthday' => '1997-06-20', 'gender' => 'female'],
        ],
    ]));
    $reservation = $result->reservation;

    expect($reservation->participants)->toHaveCount(2)
        ->and($reservation->bookerIsTraveling())->toBeFalse()
        ->and($reservation->bookerParticipant)->toBeNull()
        ->and($reservation->contactName())->toBe('Ada Reyes')
        // the booker's name appears in no seat
        ->and($reservation->participants->pluck('first_name')->all())->not->toContain('Ada');
});

it('stores optional per-participant contact details', function () {
    $result = $this->svc->register($this->departure, regData([
        'party_size' => 2,
        'participants' => [
            ['first_name' => 'Ada', 'last_name' => 'Reyes', 'birthday' => '1998-04-02', 'gender' => 'female'],
            ['first_name' => 'Ben', 'last_name' => 'Cruz', 'birthday' => '1995-01-10', 'gender' => 'male', 'phone' => '09998887777'],
        ],
    ]));

    expect($result->reservation->participants[1]->phone)->toBe('09998887777');
});

it('creates a passwordless account from the booker, not from a traveler', function () {
    $result = $this->svc->register($this->departure, regData([
        'party_size' => 1,
        'booker_is_traveling' => false,
        'participants' => [['first_name' => 'Ben', 'last_name' => 'Cruz', 'birthday' => '1995-01-10']],
        'create_account' => true,
    ]));

    $user = User::where('email', 'ada@example.com')->first();

    expect($result->outcome)->toBe(AccountOutcome::AccountCreated)
        ->and($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Ada')      // the booker
        ->and($user->password)->toBeNull()
        ->and($user->role)->toBe('joiner')
        ->and($result->reservation->joiner_id)->toBe($user->id)
        ->and($result->magicUrl)->toContain('/magic/');
});

it('never attaches a booking to an existing account by email alone', function () {
    $existing = User::factory()->create(['email' => 'ada@example.com', 'role' => 'joiner']);

    $result = $this->svc->register($this->departure, regData(['create_account' => true]));

    expect($result->outcome)->toBe(AccountOutcome::EmailTaken)
        ->and($result->reservation->joiner_id)->toBeNull()   // stays a guest booking
        ->and($result->magicUrl)->toContain('/magic/')
        ->and(auth()->check())->toBeFalse();

    expect(MagicLink::where('purpose', 'attach')->count())->toBe(1);
});

it('attaches directly for a signed-in joiner and fills only their blank profile fields', function () {
    $actor = User::factory()->create([
        'role' => 'joiner', 'first_name' => 'Adelaida', 'birthday' => null,
    ]);

    $result = $this->svc->register($this->departure, regData(['actor' => $actor]));

    expect($result->outcome)->toBe(AccountOutcome::AlreadySignedIn)
        ->and($result->reservation->joiner_id)->toBe($actor->id);

    $actor->refresh();

    expect($actor->birthday->format('Y-m-d'))->toBe('1998-04-02')  // was blank, now filled
        ->and($actor->first_name)->toBe('Adelaida');               // already set, not overwritten
});

it('refuses a host account', function () {
    $host = User::factory()->create(['role' => 'host']);

    $this->svc->register($this->departure, regData(['actor' => $host]));
})->throws(HostCannotJoinException::class);

it('still cannot oversell through the new path', function () {
    $small = TripDeparture::factory()->create(['capacity' => 2]);

    $this->svc->register($small, regData(['party_size' => 2, 'participants' => [[], []]]));
    $this->svc->register($small, regData(['party_size' => 1, 'participants' => [[]]]));
})->throws(TripFullException::class);

it('falls back to EmailTaken when it loses a race to the same brand-new email', function () {
    // DB::listen fires *after* a query runs but *before* the calling code inspects the result,
    // which is precisely the race window: the existence check has already returned "free" when
    // the rival account appears. Real-world trigger is a double-tapped submit button.
    $planted = false;

    DB::listen(function ($query) use (&$planted) {
        if ($planted || ! str_contains($query->sql, 'from "users"')) {
            return;
        }

        $planted = true;
        User::factory()->create(['email' => 'ada@example.com', 'role' => 'joiner']);
    });

    $result = $this->svc->register($this->departure, regData(['create_account' => true]));

    expect($planted)->toBeTrue()                                  // the race actually happened
        ->and($result->outcome)->toBe(AccountOutcome::EmailTaken)
        ->and($result->reservation->joiner_id)->toBeNull()         // stays a guest booking
        ->and($result->magicUrl)->toContain('/magic/')
        ->and(User::where('email', 'ada@example.com')->count())->toBe(1);
});
