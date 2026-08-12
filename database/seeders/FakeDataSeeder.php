<?php

namespace Database\Seeders;

use App\Models\HostProfile;
use App\Models\Place;
use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\Trip;
use App\Models\TripDeparture;
use App\Models\User;
use App\Models\WaitlistEntry;
use App\Services\ReservationService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Bulk fake data: host accounts, joiner accounts, and bookings across every state.
 *
 * Distinct from DemoSeeder, which builds a small hand-shaped set for demoing the happy path.
 * This one exists to fill the app up — enough hosts to paginate the directory, enough bookings
 * per departure that a roster is worth filtering, and at least one booking sitting in each
 * status so no screen has to be reasoned about from an empty state.
 *
 * ── Why this goes through ReservationService rather than writing rows ──
 *
 * Reservation state is not just a `status` column. Marking a booking paid mints a pass code for
 * every traveller on it; cancelling snapshots what was paid into `refund_due` before the status
 * moves; confirming verifies an existing payment submission and deliberately does not invent one.
 * A seeder that INSERTs directly can produce a row that no code path could ever have created —
 * a fully-paid booking whose travellers have no passes, say — and then the pass page is blank
 * and you go looking for a bug in the pass page. Driving the real service keeps the fake data
 * honest about what the UI will actually show.
 *
 * Mail is faked for the run: those same transitions each send, and seeding a few hundred
 * bookings would otherwise write a few hundred messages to the log for nobody to read.
 */
class FakeDataSeeder extends Seeder
{
    /*
     * Volume, as writable properties rather than constants.
     *
     * `$this->callWith(FakeDataSeeder::class, [...])` cannot reach a constant, and a test that
     * has to build 500 bookings to check the seeder still runs is a test nobody keeps. Set these
     * before run() to seed a smaller world:
     *
     *     $seeder = new FakeDataSeeder();
     *     $seeder->hosts = 2;
     *     $seeder->joiners = 6;
     *     $seeder->run();
     */
    public int $hosts = 12;

    public int $joiners = 60;

    /** @var array{int, int} */
    public array $tripsPerHost = [2, 4];

    /** @var array{int, int} */
    public array $departuresPerTrip = [2, 4];

    public function run(): void
    {
        Mail::fake();

        // Places are the catalogue's backbone; without them trips have nowhere to be.
        if (Place::count() === 0) {
            $this->call(PlaceSeeder::class);
        }

        $places = Place::all();

        /*
         * Accounts are numbered from whatever is already there.
         *
         * The emails are predictable on purpose — host3@terra.test is a login you can type from
         * memory — but predictable means collidable, so a second run has to start counting where
         * the first stopped rather than trying to create host1 again.
         */
        $hostOffset = User::where('role', 'host')->count();
        $joinerOffset = User::where('role', 'joiner')->count();

        $joiners = $this->joinerAccounts($joinerOffset);
        $this->admin();

        $hosts = collect(range(1, $this->hosts))->map(fn (int $i) => $this->host($hostOffset + $i));

        foreach ($hosts as $host) {
            $tripCount = fake()->numberBetween(...$this->tripsPerHost);

            for ($t = 0; $t < $tripCount; $t++) {
                $trip = $this->trip($host, $places->random());

                $departureCount = fake()->numberBetween(...$this->departuresPerTrip);

                for ($d = 0; $d < $departureCount; $d++) {
                    $departure = $this->departure($trip, $d);
                    $this->fillDeparture($departure, $host, $joiners);
                }
            }
        }

        $this->command?->info(sprintf(
            'Seeded %d hosts, %d joiners, %d trips, %d departures, %d bookings.',
            $this->hosts, $this->joiners, Trip::count(), TripDeparture::count(), Reservation::count()
        ));
    }

    /* ───────────────────────── accounts ───────────────────────── */

    /**
     * One admin, so the moderation console is reachable without hand-editing a role.
     *
     * firstOrCreate because there is only ever one of these — a second run should find the
     * existing moderator, not make a rival.
     */
    private function admin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@terra.test'],
            ['name' => 'Terra Admin', 'role' => 'admin', 'email_verified_at' => now()]
        );
    }

    private function host(int $i): User
    {
        $name = fake()->randomElement(['Ridgeline', 'Sunchaser', 'Highland', 'Northbound', 'Salt & Summit',
            'Trailhead', 'Backcountry', 'Wayfarer', 'Alpine Co.', 'Coastwise', 'Two Peaks', 'Overland']).' '.
            fake()->randomElement(['Collective', 'Outdoors', 'Expeditions', 'Trips', 'Club']);

        // Handles are unique and public-facing, so they are derived and then suffixed rather
        // than left to chance — a collision here fails the whole seed.
        $handle = Str::slug($name).'-'.$i;

        $host = User::factory()->create([
            'name' => $name,
            'email' => 'host'.$i.'@terra.test',
            'role' => 'host',
            'handle' => $handle,
            'phone' => '09'.fake()->numerify('#########'),
        ]);

        HostProfile::factory()->for($host)->create([
            'tagline' => fake()->randomElement([
                'Cordillera traverses, small groups',
                'Weekend island hops out of Batangas',
                'Sunrise summits for first-timers',
                'Long-distance ridges, no rush',
                'Surf trips up the eastern seaboard',
            ]),
            'location' => fake()->randomElement(['Baguio', 'Manila', 'Cebu', 'Davao', 'La Union', 'Batangas']),
            'currency' => 'PHP',
            'payment_methods' => [
                ['label' => 'GCash', 'detail' => '09'.fake()->numerify('#########')],
                ['label' => 'Bank transfer', 'detail' => '•••• '.fake()->numberBetween(1000, 9999)],
            ],
        ]);

        return $host;
    }

    /** @return Collection<int, User> */
    private function joinerAccounts(int $offset = 0): Collection
    {
        return collect(range(1, $this->joiners))->map(fn (int $i) => User::factory()->create([
            'email' => 'joiner'.($offset + $i).'@terra.test',
            'role' => 'joiner',
            'phone' => '09'.fake()->numerify('#########'),
            'birthday' => fake()->dateTimeBetween('-45 years', '-18 years'),
        ]));
    }

    /* ───────────────────────── trips ───────────────────────── */

    private function trip(User $host, Place $place): Trip
    {
        $suffix = match ($place->category) {
            'island' => 'Island Escape',
            'surf' => 'Surf Trip',
            'city' => 'City Discovery',
            'beach' => 'Beach Getaway',
            default => fake()->randomElement(['Sunrise Traverse', 'Summit Trek', 'Ridge Expedition']),
        };

        $price = fake()->numberBetween(1200, 6500);
        $deposit = fake()->boolean(33) ? (int) round($price * 0.3) : null;

        /*
         * Payment terms, in all three shapes a host can express them.
         *
         * Relative hours are the common case ("48 hours after I accept you"); a fixed date suits
         * a trip where permits are bought as a group on a known day; and a good third of hosts
         * set nothing at all and chase people in the chat. All three have to exist in the seed or
         * the surfaces that render them are only ever tested one way.
         */
        $terms = fake()->randomElement(['relative', 'relative', 'fixed', 'none']);

        return Trip::factory()->published()->create([
            'host_id' => $host->id,
            'place_id' => $place->id,
            'title' => $place->name.' '.$suffix.' '.fake()->unique()->numberBetween(100, 999),
            'price' => $price,
            // A deposit trip is the only way a booking can be part paid and still owe a balance,
            // which several screens branch on.
            'deposit_amount' => $deposit,
            'downpayment_due_hours' => $terms === 'relative' && $deposit ? fake()->randomElement([24, 48, 72]) : null,
            'downpayment_due_at' => $terms === 'fixed' && $deposit ? now()->addDays(fake()->numberBetween(5, 20)) : null,
            'full_payment_due_hours' => $terms === 'relative' ? fake()->randomElement([168, 336, 720]) : null,
            'full_payment_due_at' => $terms === 'fixed' ? now()->addDays(fake()->numberBetween(21, 60)) : null,
            'requires_id' => fake()->boolean(25),
            'packing_items' => fake()->randomElements(
                ['headlamp', 'jacket', 'water', 'trail_shoes', 'warm_layer', 'sunblock', 'cash', 'daypack'],
                fake()->numberBetween(3, 6)
            ),
        ]);
    }

    /**
     * A spread of dates, so the calendars and "departing soon" have something at every distance.
     *
     * The first departure of each trip is deliberately in the past — without one, no trip has
     * reviews and every host rating is null.
     */
    private function departure(Trip $trip, int $index): TripDeparture
    {
        $start = $index === 0
            ? fake()->dateTimeBetween('-4 months', '-1 week')
            : fake()->dateTimeBetween('+3 days', '+4 months');

        $start = Carbon::parse($start)->setTime(fake()->numberBetween(4, 7), 0);

        // A quarter run more than one day, which is what puts a trip on several calendar squares.
        $days = fake()->boolean(25) ? fake()->numberBetween(2, 4) : 1;

        return TripDeparture::factory()->for($trip)->create([
            'capacity' => fake()->numberBetween(6, 18),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addDays($days - 1)->setTime(16, 0),
            'status' => $index === 0 ? 'completed' : 'open',
        ]);
    }

    /* ───────────────────────── bookings ───────────────────────── */

    /**
     * Book a departure up to somewhere between a third and nearly full.
     *
     * Never to capacity by default — a departure with no seats left cannot be booked into by
     * hand afterwards, and a database where every trip is sold out is useless for clicking around.
     * One departure in eight is filled deliberately so the waitlist has somewhere to live.
     */
    private function fillDeparture(TripDeparture $departure, User $host, Collection $joiners): void
    {
        $service = app(ReservationService::class);
        $soldOut = fake()->boolean(12);

        $target = $soldOut
            ? $departure->capacity
            : (int) round($departure->capacity * fake()->randomFloat(2, 0.35, 0.8));

        $pool = $joiners->shuffle();
        $seated = 0;
        $i = 0;

        while ($seated < $target && $i < $pool->count()) {
            $joiner = $pool[$i++];
            $party = min(fake()->boolean(25) ? 2 : 1, $target - $seated);

            try {
                $reservation = $service->reserve($departure, [
                    'joiner_id' => $joiner->id,
                    'party_size' => $party,
                    'source' => 'app',
                ]);
            } catch (\Throwable) {
                // Capacity lost a race, or the departure filled. Nothing to fix — stop adding.
                break;
            }

            $reservation->update([
                'contact_first_name' => fake()->firstName(),
                'contact_last_name' => fake()->lastName(),
                'contact_email' => $joiner->email,
                'contact_phone' => $joiner->phone,
            ]);

            $this->participants($reservation, $departure->trip);
            $this->advance($reservation, $host, $departure, $service);

            $seated += $party;
        }

        if ($soldOut) {
            $this->waitlist($departure);
        }
    }

    /** One traveller per seat. The booker is position 0 — that is what `is_booker` means here. */
    private function participants(Reservation $reservation, Trip $trip): void
    {
        for ($p = 0; $p < $reservation->party_size; $p++) {
            ReservationParticipant::factory()->for($reservation)->create([
                'position' => $p,
                'is_booker' => $p === 0,
                'first_name' => fake()->firstName(),
                'last_name' => fake()->lastName(),
                'birthday' => fake()->dateTimeBetween('-60 years', '-18 years'),
                'email' => $p === 0 ? $reservation->contact_email : null,
                'phone' => $p === 0 ? $reservation->contact_phone : null,
                // Required at registration, so every seeded seat carries one — except the odd
                // legacy-shaped row below, which is what the host's "details missing" filter is
                // there to surface.
                'emergency_contact_name' => fake()->boolean(90) ? fake()->name() : null,
                'emergency_contact_phone' => fake()->boolean(90) ? '09'.fake()->numerify('#########') : null,
                // On an ID trip, most but not all travellers have uploaded — the gap is the
                // whole reason the host-side "No ID" badge exists.
                'id_image' => $trip->requires_id && fake()->boolean(70) ? 'ids/placeholder.jpg' : null,
                'id_uploaded_at' => $trip->requires_id && fake()->boolean(70) ? now()->subDays(fake()->numberBetween(1, 20)) : null,
            ]);
        }
    }

    /**
     * Walk a fresh booking to a random point along the real lifecycle.
     *
     * The order matters and is the whole point: a seat is granted first, and only a granted seat
     * can be paid for. Nothing here may submit a payment before confirm(), because the service
     * refuses it — which is exactly the guarantee the seeder is meant to reflect rather than
     * route around.
     *
     * Past departures skip the early states: a trip that already ran cannot still be waiting on
     * the host to accept somebody.
     */
    private function advance(Reservation $reservation, User $host, TripDeparture $departure, ReservationService $service): void
    {
        $past = $departure->starts_at->isPast();

        $state = $past
            ? fake()->randomElement(['paid', 'paid', 'paid', 'paid', 'deposit', 'cancelled'])
            : fake()->randomElement([
                // Waiting on the host
                'pending', 'awaiting',
                // Seat granted, money owed — the state the new order creates most of
                'confirmed', 'confirmed', 'submitted', 'submitted', 'deposit', 'overdue',
                // Settled, or fallen over
                'paid', 'paid', 'paid', 'cancelled', 'refunded',
            ]);

        if ($state === 'pending') {
            return; // Exactly as reserve() made it: holding a seat, host has not looked yet.
        }

        if ($state === 'awaiting') {
            $reservation->update(['status' => 'awaiting_payment']);

            return;
        }

        /*
         * The seat is granted. This is what starts the payment clock — confirm() stamps the
         * trip's terms onto the booking, so the deadlines below are real resolved dates.
         */
        $service->confirm($reservation->fresh(), $host);

        if ($state === 'confirmed') {
            return; // Accepted, owes everything, has sent nothing yet.
        }

        if ($state === 'overdue') {
            // Backdate the deadline rather than the confirmation: the booking is genuinely late,
            // and the roster's overdue flag reads the stamped date, not the trip's policy.
            $reservation->fresh()->update([
                'downpayment_due_at' => now()->subDays(fake()->numberBetween(1, 6)),
                'full_payment_due_at' => now()->subDays(fake()->numberBetween(1, 3)),
            ]);

            return;
        }

        $service->submitPayment($reservation->fresh(), [
            'method' => fake()->randomElement(['GCash', 'Maya', 'Bank transfer', 'BPI']),
            'reference_no' => strtoupper(fake()->bothify('??-########')),
            'proof_image' => 'proofs/placeholder.jpg',
        ]);

        if ($state === 'submitted') {
            return; // Proof sitting on the roster, waiting for the host to look at it.
        }

        // The host looked at the proof and says the money is there. On a deposit trip this
        // credits the deposit and leaves a balance; otherwise it settles the lot.
        $service->verifyPayment($reservation->fresh(), $host);

        if ($state === 'deposit') {
            return;
        }

        if ($state === 'paid') {
            $service->markFullyPaid($reservation->fresh(), $host);

            // Some of the people on a past trip actually turned up and were scanned in.
            if ($past) {
                $reservation->fresh()->participants->each(function (ReservationParticipant $p) use ($host, $departure) {
                    if (fake()->boolean(80)) {
                        $p->update([
                            'checked_in_at' => $departure->starts_at->copy()->addMinutes(fake()->numberBetween(0, 90)),
                            'checked_in_by' => $host->id,
                        ]);
                    }
                });
            }

            return;
        }

        if ($state === 'refunded') {
            $service->markFullyPaid($reservation->fresh(), $host);
        }

        // cancel() snapshots what was paid into refund_due before it touches the status, so a
        // cancelled-after-paying booking correctly shows money owed back.
        $service->cancel($reservation->fresh(), fake()->randomElement([
            'Could not make the date', 'Family emergency', 'Weather call', 'Found another trip',
        ]));

        if ($state === 'refunded') {
            $service->markRefunded($reservation->fresh(), $host, 'Sent back via GCash');
        }
    }

    /** A queue on a full departure, one of them already told a seat opened. */
    private function waitlist(TripDeparture $departure): void
    {
        $waiting = fake()->numberBetween(2, 6);

        for ($w = 0; $w < $waiting; $w++) {
            WaitlistEntry::create([
                'trip_departure_id' => $departure->id,
                'name' => fake()->name(),
                'email' => fake()->unique()->safeEmail(),
                'phone' => '09'.fake()->numerify('#########'),
                'party_size' => fake()->boolean(20) ? 2 : 1,
                'notified_at' => $w === 0 && fake()->boolean(40) ? now()->subDay() : null,
            ]);
        }
    }
}
