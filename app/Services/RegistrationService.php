<?php

namespace App\Services;

use App\Exceptions\HostCannotJoinException;
use App\Models\Reservation;
use App\Models\TripDeparture;
use App\Models\User;
use App\Support\AccountOutcome;
use App\Support\RegistrationResult;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class RegistrationService
{
    public function __construct(
        private ReservationService $reservations,
        private MagicLinkService $magicLinks,
    ) {}

    /**
     * Book seats and record who is travelling.
     *
     * The booker and the travellers are separate: the booker always supplies contact details
     * and may or may not occupy a seat.
     *
     * @param  array{party_size:int, contact:array, booker_is_traveling:bool, participants:array, create_account:bool, actor:?User}  $data
     */
    public function register(TripDeparture $departure, array $data): RegistrationResult
    {
        $actor = $data['actor'] ?? null;

        if ($actor?->isHost()) {
            throw new HostCannotJoinException();
        }

        return DB::transaction(function () use ($departure, $data, $actor) {
            // Seats first: reserve() holds a row lock on the departure and is the only thing
            // preventing an oversell. Nesting transactions is safe — Laravel uses savepoints.
            $reservation = $this->reservations->reserve($departure, [
                'party_size' => $data['party_size'],
                'source' => 'app',
            ]);

            $contact = $data['contact'];

            $reservation->update([
                'contact_first_name' => $contact['first_name'] ?? null,
                'contact_middle_name' => $contact['middle_name'] ?? null,
                'contact_last_name' => $contact['last_name'] ?? null,
                'contact_email' => $contact['email'] ?? null,
                'contact_phone' => $contact['phone'] ?? null,
            ]);

            $this->writeParticipants($reservation, $data);

            [$outcome, $magicUrl] = $this->resolveAccount($reservation, $data, $actor);

            return new RegistrationResult($reservation->fresh(['participants']), $outcome, $magicUrl);
        });
    }

    /**
     * Exactly party_size rows, blanks included, so the host can see who is still missing.
     */
    private function writeParticipants(Reservation $reservation, array $data): void
    {
        for ($i = 0; $i < $data['party_size']; $i++) {
            $row = $data['participants'][$i] ?? [];

            $reservation->participants()->create([
                'position' => $i,
                'is_booker' => $i === 0 && ($data['booker_is_traveling'] ?? false),
                'first_name' => $row['first_name'] ?? null,
                'middle_name' => $row['middle_name'] ?? null,
                'last_name' => $row['last_name'] ?? null,
                'birthday' => $row['birthday'] ?? null,
                'gender' => $row['gender'] ?? null,
                'email' => $row['email'] ?? null,
                'phone' => $row['phone'] ?? null,
            ]);
        }
    }

    /** @return array{0: AccountOutcome, 1: ?string} */
    private function resolveAccount(Reservation $reservation, array $data, ?User $actor): array
    {
        if ($actor) {
            $reservation->update(['joiner_id' => $actor->id]);
            $this->backfillProfile($actor, $data);

            return [AccountOutcome::AlreadySignedIn, null];
        }

        if (! ($data['create_account'] ?? false)) {
            return [AccountOutcome::Guest, null];
        }

        $contact = $data['contact'];
        $existing = User::where('email', $contact['email'])->first();

        if ($existing) {
            return $this->emailTaken($existing, $reservation);
        }

        // The account is always the booker — they supplied the email and will pay.
        try {
            $user = User::create([
                'name' => trim(($contact['first_name'] ?? '').' '.($contact['last_name'] ?? '')),
                'first_name' => $contact['first_name'] ?? null,
                'middle_name' => $contact['middle_name'] ?? null,
                'last_name' => $contact['last_name'] ?? null,
                'email' => $contact['email'],
                'phone' => $contact['phone'] ?? null,
                'password' => null,
                'role' => 'joiner',
            ]);
        } catch (QueryException $e) {
            // Lost a race: between the check above and this insert, someone claimed the address.
            // A double-tapped submit button is enough to get here, and letting it through would
            // roll back the whole registration and show a 500 over a recoverable situation.
            // Re-query rather than trust the exception's shape, which differs by driver — if the
            // address is still free, this was some other failure and must not be swallowed.
            $raced = User::where('email', $contact['email'])->first();

            if (! $raced) {
                throw $e;
            }

            return $this->emailTaken($raced, $reservation);
        }

        $this->backfillProfile($user, $data);
        $reservation->update(['joiner_id' => $user->id]);

        ['url' => $url] = $this->magicLinks->issue('login', [
            'user_id' => $user->id,
            'email' => $user->email,
            'reservation_id' => $reservation->id,
        ]);

        return [AccountOutcome::AccountCreated, $url];
    }

    /**
     * The address already belongs to an account, so the booking stays a guest booking.
     *
     * Never auto-attach: that would let anyone create a booking that shows up inside a
     * stranger's account. The real owner has to click a link we send to their address.
     *
     * @return array{0: AccountOutcome, 1: ?string}
     */
    private function emailTaken(User $owner, Reservation $reservation): array
    {
        ['url' => $url] = $this->magicLinks->issue('attach', [
            'user_id' => $owner->id,
            'email' => $owner->email,
            'reservation_id' => $reservation->id,
        ]);

        return [AccountOutcome::EmailTaken, $url];
    }

    /**
     * Fill blank profile fields so the next booking pre-fills.
     *
     * Only blanks — a value the user already set is theirs, and a form submission must never
     * silently overwrite it.
     */
    private function backfillProfile(User $user, array $data): void
    {
        // Birthday and gender belong to a traveller, so take them from the booker's own seat
        // if they have one. A booker who is not travelling supplies neither.
        $ownSeat = ($data['booker_is_traveling'] ?? false) ? ($data['participants'][0] ?? []) : [];

        $candidates = [
            'first_name' => $data['contact']['first_name'] ?? null,
            'middle_name' => $data['contact']['middle_name'] ?? null,
            'last_name' => $data['contact']['last_name'] ?? null,
            'phone' => $data['contact']['phone'] ?? null,
            'birthday' => $ownSeat['birthday'] ?? null,
            'gender' => $ownSeat['gender'] ?? null,
        ];

        $fill = collect($candidates)
            ->filter(fn ($value, $key) => filled($value) && blank($user->{$key}))
            ->all();

        if ($fill !== []) {
            $user->update($fill);
        }
    }
}
