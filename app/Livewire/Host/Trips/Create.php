<?php

namespace App\Livewire\Host\Trips;

use App\Models\HostMeetupPreset;
use App\Models\Place;
use App\Models\Trip;
use App\Models\TripDraft;
use App\Services\ReservationService;
use App\Services\WaitlistService;
use App\Support\HikeGrades;
use App\Support\PackingItems;
use App\Support\PaymentTerms;
use App\Support\TrailFeatures;
use App\Support\Visibility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

class Create extends Component
{
    use WithFileUploads;

    /** 1 Where & the trail · 2 The trip · 3 What's included · 4 Logistics · 5 Publish */
    public const LAST_STEP = 5;

    public int $step = 1;

    // Step 1 — the trip
    /** @var array<int, array{place_id:int, days:list<string>}> — days is one label per day */
    public array $destinations = [];

    public string $title = '';

    public string $description = '';

    /** @var array<int, array{label:string, note:string, image:mixed}> */
    public array $inclusions = [
        ['label' => 'Transport', 'note' => '', 'image' => null],
        ['label' => 'Permits', 'note' => '', 'image' => null],
        ['label' => 'Guide', 'note' => '', 'image' => null],
    ];

    /**
     * What joiners should bring, as keys from App\Support\PackingItems.
     *
     * Allow-listed rather than free text for the same reason trail features are: one vocabulary
     * across every trip, so "headlamp" is never also "head lamp" and "flashlight".
     *
     * @var array<int, string>
     */
    public array $packing = [];

    /** Ask every traveller for a photo of their ID. Off unless the host turns it on. */
    public bool $requiresId = false;

    public $cover = null;

    /** @var array uploaded carousel photos */
    public array $photos = [];

    /**
     * Trail detail, one entry per hiking destination, keyed by place id.
     *
     * Keyed by place rather than by position so reordering or removing a stop can never
     * shift one mountain's numbers onto another.
     *
     * @var array<int, array<string, mixed>>
     */
    public array $hikes = [];

    public const BLANK_HIKE = [
        'trail_name' => '', 'jump_off' => '', 'masl' => '', 'elevation_gain_m' => '',
        'distance_km' => '', 'duration_hours' => '', 'difficulty' => '', 'trail_class' => '',
        'trail_type' => '', 'features' => [], 'pulag_factors' => [],
    ];

    // Step 2 — logistics
    /** @var array<int, array{label:string, address:string, lat:string, lng:string, note:string, image:mixed, save_to_library:bool}> */
    public array $meetups = [self::BLANK_SPOT];

    /** Where the trip ends, and when. Separate from pickups: the return leg is its own thing. */
    public array $dropoffs = [self::BLANK_SPOT];

    public array $departures = [
        ['starts_at' => '', 'ends_at' => '', 'capacity' => 12],
    ];

    public string $price = '';

    /**
     * Optional price windows. Empty means one flat price; otherwise the tier whose window
     * covers today is what a joiner is charged, so an early-bird rate expires on its own.
     *
     * @var array<int, array{label:string, amount:string, starts_at:string, ends_at:string}>
     */
    public array $priceTiers = [];

    /** Blank = pay in full. A figure holds a place and the balance is settled later. */
    public string $deposit = '';

    /*
     * Payment deadlines, one per payment type.
     *
     * Each is a mode plus the field that mode uses, rather than one clever field that means
     * different things — a host switching from "48 hours" to "on 1 September" and back should
     * find their earlier answer still there, not silently reinterpreted as the other kind.
     * 'none' is the default and stays the common case: plenty of hosts just chase people in chat.
     */
    public string $downpaymentDueMode = 'none';

    public string $downpaymentDueHours = '';

    public string $downpaymentDueDate = '';

    public string $fullDueMode = 'none';

    public string $fullDueHours = '';

    public string $fullDueDate = '';

    // Step 5 — publish: who can find the trip in a listing. Listing control only; its link opens for anyone.
    public string $visibility = Visibility::PUBLIC;

    /** A Visibility::WINDOWS key, or 'keep' to leave the opening date an edited followers-first schedule already has. */
    public string $followersWindow = self::DEFAULT_WINDOW;

    /** When the schedule being edited opens to everyone, as the host reads it ("5 Dec, 6:00 pm"). Labels the 'keep' option and nothing else. */
    public ?string $currentOpensAt = null;

    /** The window a followers-first trip gets unless the host picks another. */
    private const DEFAULT_WINDOW = '3d';

    /**
     * Canonical shape for a pickup / drop-off row.
     *
     * Every row that enters state — a fresh one, a preset, a restored draft, a copied
     * trip — is merged over this. Hand-built rows are how `time` went missing from
     * copied trips and crashed the summary with "Undefined array key".
     */
    public const BLANK_SPOT = [
        'label' => '', 'address' => '', 'lat' => '', 'lng' => '', 'time' => '',
        'note' => '', 'image' => null, 'save_to_library' => false,
    ];

    /** @param  iterable<array<string, mixed>>  $rows */
    private function spotRows(iterable $rows, array $fallback): array
    {
        $normalised = collect($rows)
            ->map(fn ($row) => array_merge(self::BLANK_SPOT, array_intersect_key((array) $row, self::BLANK_SPOT)))
            ->values()
            ->all();

        return $normalised ?: $fallback;
    }

    // ── destinations ────────────────────────────────────────────────
    public function addDestination(int $placeId): void
    {
        if (collect($this->destinations)->contains('place_id', $placeId)) {
            return;
        }

        // every stop is at least a day; the host adds more and describes each one
        $this->destinations[] = ['place_id' => $placeId, 'days' => ['']];

        $this->prefillFromPlace(Place::find($placeId));
    }

    /** Places on this trip that are hikes, in itinerary order. One trail card each. */
    public function getHikeStopsProperty()
    {
        $ids = collect($this->destinations)->pluck('place_id')->filter();

        if ($ids->isEmpty()) {
            return collect();
        }

        $places = Place::whereIn('id', $ids)->where('category', 'hike')->get()->keyBy('id');

        return $ids->map(fn ($id) => $places[$id] ?? null)->filter()->values();
    }

    /**
     * Copy across what the app already knows about the destination. Height and region are
     * properties of the mountain, not of this trip — asking the host to retype them is
     * busywork. Only ever fills blanks: anything the host has typed wins.
     *
     * @var list<string> fields filled from the place, so the view can mark them as such
     */
    public array $prefilled = [];

    protected function prefillFromPlace(?Place $place): void
    {
        if (! $place) {
            return;
        }

        if (blank($this->title)) {
            $this->title = $place->name;
            $this->prefilled[] = 'title';
        }

        if ($place->category !== 'hike') {
            return;
        }

        $this->hikes[$place->id] ??= self::BLANK_HIKE;

        if ($place->elevation_m && blank($this->hikes[$place->id]['masl'])) {
            $this->hikes[$place->id]['masl'] = (string) $place->elevation_m;
            $this->prefilled[] = "hikes.{$place->id}.masl";
        }

        $this->prefilled = array_values(array_unique($this->prefilled));
    }

    /** The host edited a prefilled field, so stop calling it prefilled. */
    public function updated(string $key): void
    {
        $this->prefilled = array_values(array_diff($this->prefilled, [$key]));

        $this->saveDraft();
    }

    public function addDay(int $i): void
    {
        $this->destinations[$i]['days'][] = '';
        $this->saveDraft();
    }

    public function removeDay(int $i, int $d): void
    {
        // a stop always keeps at least one day, or it stops being a stop
        if (count($this->destinations[$i]['days'] ?? []) <= 1) {
            return;
        }

        unset($this->destinations[$i]['days'][$d]);
        $this->destinations[$i]['days'] = array_values($this->destinations[$i]['days']);
        $this->saveDraft();
    }

    public function removeDestination(int $i): void
    {
        unset($this->destinations[$i]);
        $this->destinations = array_values($this->destinations);
    }

    public function moveDestination(int $i, int $direction): void
    {
        $target = $i + $direction;

        if (! isset($this->destinations[$i], $this->destinations[$target])) {
            return;
        }

        [$this->destinations[$i], $this->destinations[$target]] = [$this->destinations[$target], $this->destinations[$i]];
    }

    // ── repeaters ───────────────────────────────────────────────────
    public function addInclusion(): void
    {
        $this->inclusions[] = ['label' => '', 'note' => '', 'image' => null];
    }

    public function removeInclusion(int $i): void
    {
        unset($this->inclusions[$i]);
        $this->inclusions = array_values($this->inclusions);
    }

    public function addMeetup(): void
    {
        $this->meetups[] = self::BLANK_SPOT;
    }

    public function removeMeetup(int $i): void
    {
        unset($this->meetups[$i]);
        $this->meetups = array_values($this->meetups);
    }

    /** Add a blank spot, or one seeded from the library. `$kind` is pickup|dropoff. */
    public function addSpot(string $kind, ?int $presetId = null): void
    {
        $rows = $kind === 'dropoff' ? 'dropoffs' : 'meetups';

        $this->{$rows}[] = self::BLANK_SPOT;

        if ($presetId) {
            $this->applyPresetTo($rows, array_key_last($this->{$rows}), $presetId);
        }

        $this->saveDraft();
    }

    /** Copy a saved preset's values into a meetup row. Values are copied, never linked. */
    public function applyPreset(int $i, int $presetId): void
    {
        $this->applyPresetTo('meetups', $i, $presetId);
    }

    public function applyPresetTo(string $rows, int $i, int $presetId): void
    {
        $preset = Auth::user()->meetupPresets()->findOrFail($presetId);

        // keep the time the host already entered — the preset describes the place, not the hour
        $this->{$rows}[$i] = array_merge(self::BLANK_SPOT, [
            'label' => $preset->label,
            'address' => (string) $preset->address,
            'lat' => (string) ($preset->lat ?? ''),
            'lng' => (string) ($preset->lng ?? ''),
            'time' => (string) ($this->{$rows}[$i]['time'] ?? ''),
            'note' => (string) $preset->note,
        ]);

        $this->saveDraft();
    }

    public function addDropoff(): void
    {
        $this->dropoffs[] = self::BLANK_SPOT;
    }

    public function removeDropoff(int $i): void
    {
        unset($this->dropoffs[$i]);
        $this->dropoffs = array_values($this->dropoffs);
    }

    public function addTier(): void
    {
        // first tier defaults to the flat price so the host edits rather than retypes
        $this->priceTiers[] = [
            'label' => $this->priceTiers === [] ? 'Early bird' : 'Regular',
            'amount' => $this->priceTiers === [] ? $this->price : '',
            'starts_at' => '',
            'ends_at' => '',
        ];
        $this->saveDraft();
    }

    public function removeTier(int $i): void
    {
        unset($this->priceTiers[$i]);
        $this->priceTiers = array_values($this->priceTiers);
        $this->saveDraft();
    }

    public function addDeparture(): void
    {
        $this->departures[] = ['starts_at' => '', 'ends_at' => '', 'capacity' => 12];
    }

    public function removeDeparture(int $i): void
    {
        unset($this->departures[$i]);
        $this->departures = array_values($this->departures);
    }

    /**
     * Rules for an image field that may hold either a fresh upload or a storage path
     * carried over from a copied trip. A path is already stored and was validated when it
     * was first uploaded, so it passes straight through.
     */
    private function imageRules(int $maxKb = 5120): array
    {
        return ['nullable', function (string $attribute, $value, \Closure $fail) use ($maxKb) {
            if ($value === null || is_string($value)) {
                return;
            }

            $check = validator(['f' => $value], ['f' => ['image', 'max:'.$maxKb]]);

            if ($check->fails()) {
                $fail($check->errors()->first('f'));
            }
        }];
    }

    /**
     * Put an image on the public disk and return its path.
     *
     * A copied trip hands us the original's path. We copy the file rather than pointing
     * both trips at it, so removing one trip's photo can never blank the other's.
     */
    private function storeImage(mixed $value, string $dir): ?string
    {
        if ($value instanceof TemporaryUploadedFile) {
            return $value->store($dir, 'public');
        }

        if (! is_string($value) || $value === '') {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($value)) {
            return null;
        }

        $copy = $dir.'/'.Str::random(40).'.'.pathinfo($value, PATHINFO_EXTENSION);
        $disk->copy($value, $copy);

        return $copy;
    }

    // ── validation ──────────────────────────────────────────────────
    protected function validationAttributes(): array
    {
        return [
            'destinations' => 'destinations',
            'title' => 'trip title',
            'price' => 'price',
            'cover' => 'cover photo',
            'photos.*' => 'photo',
            'inclusions.*.label' => 'inclusion name',
            'inclusions.*.image' => 'inclusion photo',
            'meetups.*.label' => 'meetup name',
            'meetups.*.address' => 'address',
            'meetups.*.lat' => 'latitude',
            'meetups.*.lng' => 'longitude',
            'meetups.*.image' => 'meetup photo',
            'departures.*.starts_at' => 'start date',
            'departures.*.capacity' => 'capacity',
            'destinations.*.days' => 'days at this stop',
            'destinations.*.days.*' => 'day description',
            'deposit' => 'down payment',
            'priceTiers.*.label' => 'tier name',
            'priceTiers.*.amount' => 'tier price',
            'priceTiers.*.starts_at' => 'tier start date',
            'priceTiers.*.ends_at' => 'tier end date',
            'meetups.*.time' => 'pickup time',
            'dropoffs.*.label' => 'drop-off name',
            'dropoffs.*.address' => 'drop-off address',
            'dropoffs.*.time' => 'drop-off time',
            'dropoffs.*.lat' => 'drop-off latitude',
            'dropoffs.*.lng' => 'drop-off longitude',
            'dropoffs.*.image' => 'drop-off photo',
            'hike.trail_name' => 'trail name',
            'hike.jump_off' => 'jump-off point',
            'hike.masl' => 'summit height',
            'hike.elevation_gain_m' => 'elevation gain',
            'hike.distance_km' => 'trail distance',
            'hike.duration_hours' => 'trail duration',
            'hike.difficulty' => 'trail difficulty',
            'hike.trail_class' => 'trail class',
            'hike.trail_type' => 'trail shape',
        ];
    }

    protected function messages(): array
    {
        return [
            'deposit.lte' => 'A down payment cannot be more than the full price.',
            'destinations.required' => 'Add at least one destination.',
            'destinations.min' => 'Add at least one destination.',
            'departures.*.starts_at.required' => 'Every departure needs a start date.',
            'meetups.*.lat.between' => "That doesn't look like a valid map pin. Copy the coordinates from Google Maps.",
            'meetups.*.lng.between' => "That doesn't look like a valid map pin. Copy the coordinates from Google Maps.",
            'meetups.*.lat.numeric' => 'The latitude should be a number like 14.6220.',
            'meetups.*.lng.numeric' => 'The longitude should be a number like 121.0530.',
            'dropoffs.*.lat.between' => "That doesn't look like a valid map pin. Copy the coordinates from Google Maps.",
            'dropoffs.*.lng.between' => "That doesn't look like a valid map pin. Copy the coordinates from Google Maps.",
            'hike.masl.integer' => 'Give the summit height in whole metres, like 2922.',
            'hike.distance_km.numeric' => 'Give the distance in kilometres, like 14.5.',
            'hike.duration_hours.numeric' => 'Give the trail time in hours, like 9 or 4.5.',
        ];
    }

    /** Step 1 — where the trip goes, how long at each stop, and the trail for each hike. */
    protected function validateWhere(): void
    {
        $this->validate([
            'destinations' => ['required', 'array', 'min:1'],
            'destinations.*.place_id' => ['required', 'exists:places,id'],
            'destinations.*.days' => ['array', 'min:1', 'max:60'],
            'destinations.*.days.*' => ['nullable', 'string', 'max:120'],
            'hikes.*.trail_name' => ['nullable', 'string', 'max:80'],
            'hikes.*.jump_off' => ['nullable', 'string', 'max:120'],
            'hikes.*.masl' => ['nullable', 'integer', 'min:0', 'max:9000'],
            'hikes.*.elevation_gain_m' => ['nullable', 'integer', 'min:0', 'max:9000'],
            'hikes.*.distance_km' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'hikes.*.duration_hours' => ['nullable', 'numeric', 'min:0', 'max:200'],
            'hikes.*.difficulty' => ['nullable', 'integer', 'between:1,9'],
            'hikes.*.trail_class' => ['nullable', 'integer', 'between:1,5'],
            'hikes.*.trail_type' => ['nullable', Rule::in(array_keys(HikeGrades::TRAIL_TYPE))],
            'hikes.*.features' => ['array'],
            'hikes.*.features.*' => [Rule::in(TrailFeatures::keys())],
            'hikes.*.pulag_factors' => ['array'],
            'hikes.*.pulag_factors.*' => [Rule::in(array_keys(HikeGrades::PULAG_FACTORS))],
        ]);
    }

    /** Step 2 — how the trip is presented and what it costs. */
    protected function validateTrip(): void
    {
        $this->validate([
            'title' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cover' => $this->imageRules(),
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => $this->imageRules(8192),
            'price' => ['required', 'numeric', 'min:0'],
            'deposit' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            // Only the field its own mode actually uses is required, so switching modes never
            // strands a host behind a complaint about a box they cannot see.
            'downpaymentDueHours' => [
                Rule::requiredIf($this->downpaymentDueMode === 'relative' && $this->deposit !== ''),
                'nullable', 'integer', 'min:1', 'max:'.PaymentTerms::MAX_HOURS,
            ],
            'downpaymentDueDate' => [
                Rule::requiredIf($this->downpaymentDueMode === 'fixed' && $this->deposit !== ''),
                'nullable', 'date',
            ],
            'fullDueHours' => [
                Rule::requiredIf($this->fullDueMode === 'relative'),
                'nullable', 'integer', 'min:1', 'max:'.PaymentTerms::MAX_HOURS,
            ],
            'fullDueDate' => [
                Rule::requiredIf($this->fullDueMode === 'fixed'),
                'nullable', 'date',
            ],
            'priceTiers' => ['array', 'max:6'],
            'priceTiers.*.label' => ['required_with:priceTiers.*.amount', 'nullable', 'string', 'max:40'],
            'priceTiers.*.amount' => ['required_with:priceTiers.*.label', 'nullable', 'numeric', 'min:0'],
            'priceTiers.*.starts_at' => ['nullable', 'date'],
            'priceTiers.*.ends_at' => ['nullable', 'date', 'after_or_equal:priceTiers.*.starts_at'],
        ]);
    }

    /** Step 2 — what joiners get. */
    protected function validateInclusions(): void
    {
        $this->validate([
            'inclusions.*.label' => ['nullable', 'string', 'max:60'],
            'inclusions.*.note' => ['nullable', 'string', 'max:160'],
            'inclusions.*.image' => $this->imageRules(),
        ]);
    }

    /** Does any chosen destination make this a hike? Drives the trail section's visibility. */
    public function getIsHikeProperty(): bool
    {
        $ids = collect($this->destinations)->pluck('place_id')->filter();

        return $ids->isNotEmpty()
            && Place::whereIn('id', $ids)->where('category', 'hike')->exists();
    }

    /**
     * Toggle a packing item.
     *
     * Silently ignores a key that is not in the catalog. The method is reachable from the browser
     * whatever the markup renders, and an unknown key would be stored and then quietly dropped by
     * PackingItems::resolve() — a host would see it save and never appear again.
     */
    public function togglePacking(string $key): void
    {
        if (! in_array($key, PackingItems::keys(), true)) {
            return;
        }

        $this->packing = in_array($key, $this->packing, true)
            ? array_values(array_diff($this->packing, [$key]))
            : [...$this->packing, $key];

        $this->saveDraft();
    }

    public function toggleFeature(int $placeId, string $key): void
    {
        $this->hikes[$placeId] ??= self::BLANK_HIKE;
        $features = $this->hikes[$placeId]['features'];

        $this->hikes[$placeId]['features'] = in_array($key, $features, true)
            ? array_values(array_diff($features, [$key]))
            : [...$features, $key];

        $this->saveDraft();
    }

    /** Step 3 — where, when, how much. */
    protected function validateLogistics(): void
    {
        $this->validate([
            'departures' => ['required', 'array', 'min:1'],
            'departures.*.starts_at' => ['required', 'date'],
            'departures.*.capacity' => ['required', 'integer', 'min:1', 'max:200'],
            'meetups.*.label' => ['nullable', 'string', 'max:80'],
            'meetups.*.address' => ['nullable', 'string', 'max:160'],
            'meetups.*.note' => ['nullable', 'string', 'max:160'],
            'meetups.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'meetups.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'meetups.*.time' => ['nullable', 'date_format:H:i'],
            'dropoffs.*.label' => ['nullable', 'string', 'max:80'],
            'dropoffs.*.address' => ['nullable', 'string', 'max:160'],
            'dropoffs.*.note' => ['nullable', 'string', 'max:160'],
            'dropoffs.*.time' => ['nullable', 'date_format:H:i'],
            'dropoffs.*.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'dropoffs.*.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'dropoffs.*.image' => $this->imageRules(),
            'meetups.*.image' => $this->imageRules(),
        ]);
    }

    /** Step 5 — who can find the trip. `required` too: an empty string would slip past `in`. */
    protected function validateListing(): void
    {
        $this->validate([
            'visibility' => ['required', Rule::in(Visibility::keys())],
            'followersWindow' => ['required', Rule::in([...array_keys(Visibility::WINDOWS), 'keep'])],
        ]);
    }

    /**
     * Validate the current step and advance. Steps validate themselves, so inserting or
     * reordering a step never renames a method the views and tests depend on.
     */
    public function next(): void
    {
        match ($this->step) {
            1 => $this->validateWhere(),
            2 => $this->validateTrip(),
            3 => $this->validateInclusions(),
            4 => $this->validateLogistics(),
            default => null,
        };

        $this->step = min(self::LAST_STEP, $this->step + 1);
    }

    /** Jump to a step from the review screen. Never validates: going back is not a commitment. */
    public function goToStep(int $step): void
    {
        $this->step = max(1, min(self::LAST_STEP, $step));
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    /** Soft checklist shown on step 3. Never blocks publishing. */
    public function readiness(): array
    {
        return array_values(array_filter([
            ['Cover photo', (bool) $this->cover],
            ['Carousel photos', count($this->photos) > 0],
            ['Description', filled($this->description)],
            ['Inclusions', collect($this->inclusions)->filter(fn ($c) => filled($c['label'] ?? null))->isNotEmpty()],
            ['Meetup point', collect($this->meetups)->filter(fn ($m) => filled($m['label'] ?? null))->isNotEmpty()],
            // Only a hike can be missing trail detail, so only a hike is nudged about it.
            $this->isHike
                ? ['Trail detail', $this->hikeStops->every(fn ($p) => $this->trailFilledCount($p->id) > 0)]
                : null,
        ]));
    }

    public function publish()
    {
        // copyFrom() refuses a taken-down trip when the form opens. An open form, or its saved draft, can outlive that.
        if ($this->copiedFromId && Trip::find($this->copiedFromId)?->isTakenDown()) {
            $this->addError('copiedFromId', 'The trip this was copied from has been taken down, so this copy cannot be published.');

            return null;
        }

        $this->validateWhere();
        $this->validateTrip();
        $this->validateInclusions();
        $this->validateLogistics();
        $this->validateListing();

        $inclusions = array_values(array_filter($this->inclusions, fn ($c) => filled($c['label'] ?? null)));
        $meetups = array_values(array_filter($this->meetups, fn ($m) => filled($m['label'] ?? null)));
        $dropoffs = array_values(array_filter($this->dropoffs, fn ($d) => filled($d['label'] ?? null)));

        $trip = DB::transaction(function () use ($inclusions, $meetups, $dropoffs) {
            // One trip per schedule. Every date gets its own row so a host can later give
            // the December run a different van, guide or price from the March one; the
            // shared group id keeps them presentable as a single offering.
            // Editing updates the schedule in place. The trip is never deleted and
            // recreated: reservations hang off its departure, and a fresh row would
            // orphan every booking on it.
            if ($this->isEditing) {
                $trip = Trip::where('host_id', Auth::id())->findOrFail($this->editingId);

                return $this->applyTo($trip, $this->departures[0], $inclusions, $meetups, $dropoffs, saveToLibrary: true, listing: $this->listingAttributes($trip));
            }

            $group = (string) Str::uuid();
            // once for the lot: dates published together share one choice and one opening date
            $listing = $this->listingAttributes(null);
            $built = [];

            foreach ($this->departures as $departure) {
                // unsaved: applyTo fills the required columns and saves it
                $trip = new Trip(['host_id' => Auth::id(), 'trip_group_id' => $group]);

                // library saves only on the first schedule: three dates should not put
                // three copies of the same pickup point in the host's library
                $built[] = $this->applyTo($trip, $departure, $inclusions, $meetups, $dropoffs, saveToLibrary: $built === [], listing: $listing);
            }

            return $built[0];
        });

        TripDraft::where('host_id', Auth::id())->delete();

        // The trip page this lands on is where a new trip gets sent out, so it opens its share panel for that trip.
        // Saving a change to a trip that has been out for weeks is not a launch.
        if (! $this->isEditing) {
            session()->flash('just_published', $trip->id);
        }

        // A taken-down trip has no public page to land on (it is a 404), so its host goes back to their list.
        if ($trip->status !== 'published') {
            return $this->redirectRoute('host.trips.index', navigate: true);
        }

        return $this->redirectRoute('trips.show', ['trip' => $trip->slug], navigate: true);
    }

    /**
     * Write the whole form onto one trip and its single schedule.
     *
     * Used for both create and edit. Child rows follow the codebase's delete-and-reinsert
     * convention; the departure is updated rather than replaced so its reservations
     * survive an edit.
     */
    private function applyTo(Trip $trip, array $departure, array $inclusions, array $meetups, array $dropoffs, bool $saveToLibrary, array $listing): Trip
    {
        // Read before the fill below overwrites it. An unsaved row has no status yet; "archived" was published once.
        $neverPublished = in_array($trip->status, [null, 'draft'], true);

        $trip->fill([
            'place_id' => $this->destinations[0]['place_id'],
            'title' => $this->title,
            'description' => $this->description ?: null,
            'meetup_point' => $meetups[0]['label'] ?? null,
            'price' => $this->price,
            'deposit_amount' => $this->deposit !== '' ? $this->deposit : null,
            // Only the active mode's value is stored; the other is nulled rather than left
            // behind, so a trip cannot end up carrying two contradictory deadlines.
            'downpayment_due_hours' => $this->termHours('downpayment'),
            'downpayment_due_at' => $this->termDate('downpayment'),
            'full_payment_due_hours' => $this->termHours('full'),
            'full_payment_due_at' => $this->termDate('full'),
            // Re-filtered through the catalog on the way in as well as the way out, so a
            // retired key cannot survive in the column after the catalog has moved on.
            'packing_items' => array_values(array_intersect($this->packing, PackingItems::keys())) ?: null,
            'requires_id' => $this->requiresId,
            // Set once. After that moderation owns it, and a save must not put a taken-down trip back.
            ...($neverPublished ? ['status' => 'published'] : []),
            ...$listing,
        ]);

        // keep the stored cover when the host did not touch it
        if ($path = $this->storeImage($this->cover, 'covers')) {
            $trip->cover_photo = $path;
        }

        $trip->save();

        $trip->photos()->delete();
        $trip->meetups()->delete();
        $trip->inclusionItems()->delete();
        $trip->priceTiers()->delete();

        foreach (array_values(array_filter($this->priceTiers, fn ($t) => filled($t['label'] ?? null) && filled($t['amount'] ?? null))) as $i => $tier) {
            $trip->priceTiers()->create([
                'label' => $tier['label'],
                'amount' => $tier['amount'],
                'starts_at' => ($tier['starts_at'] ?? '') ?: null,
                'ends_at' => ($tier['ends_at'] ?? '') ?: null,
                'position' => $i,
            ]);
        }

        $trip->syncDestinations($this->destinations);

        // Trail detail hangs off each stop, so a multi-mountain trip keeps one set of
        // numbers per summit. Only written where the host (or the prefill) has content.
        foreach ($trip->destinations()->get() as $destination) {
            if (! isset($this->hikes[$destination->place_id]) || ! $destination->isHike()) {
                continue;
            }

            $row = collect($this->hikeFor($destination->place_id))
                ->map(fn ($v) => is_array($v) ? array_values($v) : ($v === '' ? null : $v))
                ->all();

            if (collect($row)->filter(fn ($v) => is_array($v) ? $v !== [] : $v !== null)->isNotEmpty()) {
                $destination->hikeDetail()->create($row);
            }
        }

        foreach (array_values($this->photos) as $i => $photo) {
            $trip->photos()->create(['path' => $this->storeImage($photo, 'trips'), 'position' => $i]);
        }

        // Exactly one departure per trip — that is the whole point of the split.
        // Updated, not replaced: reservations reference this row by id.
        $existing = $trip->departures()->first();

        // The date is fixed once people can book it. The view hides the input, but the
        // lock has to live here: a tampered payload would otherwise move a date out
        // from under everyone who already reserved a place on it.
        $dates = $this->isEditing && $existing
            ? ['starts_at' => $existing->starts_at, 'ends_at' => $existing->ends_at]
            : ['starts_at' => $departure['starts_at'], 'ends_at' => $departure['ends_at'] ?: null];

        $saved = $trip->departures()->updateOrCreate(
            ['id' => $existing?->id],
            $dates + ['capacity' => (int) $departure['capacity']],
        );

        // Open or full follows the seats, and an edit can change capacity. A new row comes out open, and a
        // cancelled or completed one is left alone.
        app(ReservationService::class)->syncDepartureFullness($saved);

        // Added seats are news to anyone waiting for one. They are told once the save has committed, so a save that
        // fails part way mails nobody.
        if ($existing && $saved->capacity > $existing->capacity) {
            DB::afterCommit(fn () => app(WaitlistService::class)->notifyAvailable($saved->fresh()));
        }

        foreach ($inclusions as $i => $c) {
            $trip->inclusionItems()->create([
                'label' => $c['label'],
                'note' => $c['note'] ?: null,
                'image' => $this->storeImage($c['image'] ?? null, 'inclusions'),
                'position' => $i,
            ]);
        }

        foreach ($meetups as $i => $m) {
            $image = $this->storeImage($m['image'] ?? null, 'meetups');

            $trip->meetups()->create([
                'kind' => 'pickup',
                'label' => $m['label'],
                'address' => $m['address'] ?: null,
                'lat' => $m['lat'] !== '' ? $m['lat'] : null,
                'lng' => $m['lng'] !== '' ? $m['lng'] : null,
                'time' => ($m['time'] ?? '') ?: null,
                'note' => $m['note'] ?: null,
                'image' => $image,
                'position' => $i,
            ]);

            if ($saveToLibrary && ($m['save_to_library'] ?? false)) {
                HostMeetupPreset::create([
                    'host_id' => Auth::id(),
                    'label' => $m['label'],
                    'address' => $m['address'] ?: null,
                    'lat' => $m['lat'] !== '' ? $m['lat'] : null,
                    'lng' => $m['lng'] !== '' ? $m['lng'] : null,
                    'note' => $m['note'] ?: null,
                    'image' => $image,
                ]);
            }
        }

        foreach (array_values($dropoffs) as $i => $d) {
            $image = $this->storeImage($d['image'] ?? null, 'meetups');

            $trip->meetups()->create([
                'kind' => 'dropoff',
                'label' => $d['label'],
                'address' => ($d['address'] ?? '') ?: null,
                'lat' => ($d['lat'] ?? '') !== '' ? $d['lat'] : null,
                'lng' => ($d['lng'] ?? '') !== '' ? $d['lng'] : null,
                'time' => ($d['time'] ?? '') ?: null,
                'note' => ($d['note'] ?? '') ?: null,
                'image' => $image,
                'position' => $i,
            ]);

            if ($saveToLibrary && ($d['save_to_library'] ?? false)) {
                HostMeetupPreset::create([
                    'host_id' => Auth::id(),
                    'label' => $d['label'],
                    'address' => ($d['address'] ?? '') ?: null,
                    'lat' => ($d['lat'] ?? '') !== '' ? $d['lat'] : null,
                    'lng' => ($d['lng'] ?? '') !== '' ? $d['lng'] : null,
                    'note' => ($d['note'] ?? '') ?: null,
                    'image' => $image,
                ]);
            }
        }

        // The departure exists now, so the URL can carry its date. First publish only: after that the address is on
        // shared links and printed QR codes, so no edit moves it.
        if ($neverPublished) {
            $trip->refreshSlug();
        }

        return $trip;

    }

    /**
     * The listing columns every schedule of one publish shares.
     *
     * Only followers first has an opening date. "keep" leaves the one an edited schedule already has; where
     * there is none to leave (a new trip, or a schedule that was not followers first) it means the default window.
     *
     * @return array{visibility: string, public_at: ?Carbon}
     */
    private function listingAttributes(?Trip $editing): array
    {
        if ($this->visibility !== Visibility::FOLLOWERS) {
            return ['visibility' => $this->visibility, 'public_at' => null];
        }

        $keep = $this->followersWindow === 'keep';
        $kept = ($keep && $editing?->visibility === Visibility::FOLLOWERS) ? $editing->public_at : null;

        return [
            'visibility' => Visibility::FOLLOWERS,
            'public_at' => $kept ?? Visibility::opensAt($keep ? self::DEFAULT_WINDOW : $this->followersWindow),
        ];
    }

    // ── drafts ──────────────────────────────────────────────────────
    // Everything except the uploads, which are temporary files on disk and cannot be
    // meaningfully restored into a fresh browser session.
    private const DRAFT_FIELDS = [
        'destinations', 'title', 'description',
        'inclusions', 'packing', 'requiresId', 'hikes', 'meetups', 'dropoffs', 'departures', 'price', 'priceTiers', 'deposit',
        'downpaymentDueMode', 'downpaymentDueHours', 'downpaymentDueDate',
        'fullDueMode', 'fullDueHours', 'fullDueDate',
        'visibility', 'followersWindow', 'copiedFromId',
    ];

    public bool $draftRestored = false;

    /** Set when editing an existing schedule rather than creating one. */
    #[Locked]
    public ?int $editingId = null;

    /** The trip this form was copied from. Saved with the draft, so a later takedown still reaches the copy. */
    #[Locked]
    public ?int $copiedFromId = null;

    public function mount(?Trip $from = null, ?Trip $trip = null): void
    {
        if ($trip?->exists) {
            abort_unless($trip->host_id === Auth::id(), 403);

            $this->editingId = $trip->id;
            $this->copyFrom($trip, editing: true);

            return;
        }

        if ($from?->exists) {
            $this->copyFrom($from);

            return;
        }

        $this->restoreDraft();
    }

    public function getIsEditingProperty(): bool
    {
        return $this->editingId !== null;
    }

    /** Snapshot the form. Called on every change, so a lost session costs nothing. */
    public function saveDraft(): void
    {
        // Editing an existing schedule is not a draft — autosaving it would clobber a
        // half-written new trip the host has parked.
        if ($this->isEditing) {
            return;
        }

        $payload = collect(self::DRAFT_FIELDS)
            ->mapWithKeys(fn ($f) => [$f => $this->stripUploads($this->$f)])
            ->all();

        TripDraft::updateOrCreate(
            ['host_id' => Auth::id()],
            ['payload' => $payload, 'step' => $this->step],
        );
    }

    /** Uploads are TemporaryUploadedFile objects — not JSON, and not valid next session. */
    private function stripUploads(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return collect($value)
            ->map(fn ($v) => is_array($v) ? $this->stripUploads($v) : (is_object($v) ? null : $v))
            ->all();
    }

    protected function restoreDraft(): void
    {
        $draft = TripDraft::where('host_id', Auth::id())->first();

        if (! $draft) {
            return;
        }

        foreach ($draft->payload as $field => $value) {
            if (in_array($field, self::DRAFT_FIELDS, true) && $value !== null) {
                $this->$field = $value;
            }
        }

        // A draft written before a field existed is missing that key. Normalise on the way
        // back in rather than making every reader defensive.
        $this->meetups = $this->spotRows($this->meetups, [self::BLANK_SPOT]);
        $this->dropoffs = $this->spotRows($this->dropoffs, [self::BLANK_SPOT]);
        $this->hikes = collect($this->hikes)->map(fn ($h) => array_merge(self::BLANK_HIKE, $h))->all();
        $this->destinations = collect($this->destinations)
            ->map(fn ($d) => array_merge(['place_id' => null, 'days' => ['']], $d))
            ->all();

        $this->step = $draft->step ?: 1;
        $this->draftRestored = true;
    }

    public function discardDraft(): void
    {
        TripDraft::where('host_id', Auth::id())->delete();

        $this->redirect(request()->header('Referer') ?: route('host.trips.create'), navigate: true);
    }

    /**
     * Start from a trip the host has already run. Hosts repeat the same mountains, and
     * the inclusions, meetups and trail detail are identical every time — so the only
     * thing that genuinely changes is the dates.
     */
    protected function copyFrom(Trip $trip, bool $editing = false): void
    {
        abort_unless($trip->host_id === Auth::id(), 403);
        // A copy would put what moderation took down back up at a new address. Editing the trip itself is still allowed.
        abort_if(! $editing && $trip->isTakenDown(), 403, 'This trip was taken down, so it cannot be copied.');

        $this->copiedFromId = $editing ? null : $trip->id;

        $this->title = $editing ? $trip->title : $trip->title.' (copy)';
        $this->description = (string) $trip->description;
        $this->price = (string) $trip->price;
        $this->deposit = $trip->deposit_amount !== null ? (string) $trip->deposit_amount : '';

        // Whichever column carries a value decides the mode on the way back in.
        $this->downpaymentDueMode = match (true) {
            $trip->downpayment_due_at !== null => 'fixed',
            $trip->downpayment_due_hours !== null => 'relative',
            default => 'none',
        };
        $this->downpaymentDueHours = (string) ($trip->downpayment_due_hours ?? '');
        $this->downpaymentDueDate = $trip->downpayment_due_at?->format('Y-m-d') ?? '';

        $this->fullDueMode = match (true) {
            $trip->full_payment_due_at !== null => 'fixed',
            $trip->full_payment_due_hours !== null => 'relative',
            default => 'none',
        };
        $this->fullDueHours = (string) ($trip->full_payment_due_hours ?? '');
        $this->fullDueDate = $trip->full_payment_due_at?->format('Y-m-d') ?? '';
        $this->priceTiers = $trip->priceTiers->map(fn ($t) => [
            'label' => $t->label,
            'amount' => (string) $t->amount,
            'starts_at' => $t->starts_at?->toDateString() ?: '',
            'ends_at' => $t->ends_at?->toDateString() ?: '',
        ])->all();

        $this->destinations = $trip->destinations
            ->map(fn ($d) => ['place_id' => $d->place_id, 'days' => $d->days ?: ['']])
            ->all();

        $this->inclusions = $trip->inclusionItems
            ->map(fn ($c) => ['label' => $c->label, 'note' => (string) $c->note, 'image' => $c->image])
            ->all() ?: $this->inclusions;

        $this->packing = array_values(array_intersect(
            (array) ($trip->packing_items ?? []),
            PackingItems::keys(),
        ));

        $this->requiresId = (bool) $trip->requires_id;

        // What listings do with the schedule right now, so a followers-first window that has already
        // passed reads, and saves, as Everyone. Only an edit of a schedule still inside its window can
        // keep that opening date; a copy starts its own (the default window).
        $this->visibility = $trip->effectiveVisibility();

        if ($editing && $this->visibility === Visibility::FOLLOWERS && $trip->public_at) {
            $this->followersWindow = 'keep';
            $this->currentOpensAt = $trip->public_at->format('j M, g:i a');
        }

        // Photos come across as storage paths, not uploads. The upload component previews
        // them, and publish() copies the file rather than sharing it, so deleting one
        // trip's photo can never blank another's.
        $this->cover = $trip->cover_photo;
        $this->photos = $trip->photos->pluck('path')->all();

        // Drop-offs were being lost entirely on a copy; both kinds come back now.
        $fromSpot = fn ($m) => [
            'label' => (string) $m->label,
            'address' => (string) $m->address,
            'lat' => (string) $m->lat,
            'lng' => (string) $m->lng,
            'time' => (string) $m->time_label,
            'note' => (string) $m->note,
            'image' => $m->image,
        ];

        $this->meetups = $this->spotRows($trip->meetups->map($fromSpot), $this->meetups);
        $this->dropoffs = $this->spotRows($trip->dropoffs->map($fromSpot), $this->dropoffs);

        // trail detail comes back keyed by place, so a two-summit trip keeps both
        foreach ($trip->destinations as $destination) {
            if (! $hike = $destination->hikeDetail) {
                continue;
            }

            $this->hikes[$destination->place_id] = [
                'trail_name' => (string) $hike->trail_name,
                'jump_off' => (string) $hike->jump_off,
                'masl' => (string) $hike->masl,
                'elevation_gain_m' => (string) $hike->elevation_gain_m,
                'distance_km' => (string) $hike->distance_km,
                'duration_hours' => (string) $hike->duration_hours,
                'difficulty' => (string) $hike->difficulty,
                'trail_class' => (string) $hike->trail_class,
                'trail_type' => (string) $hike->trail_type,
                'features' => $hike->features ?? [],
                'pulag_factors' => $hike->pulag_factors ?? [],
            ];
        }

        // A copy starts undated: the date is the one thing that must be new. Editing keeps
        // the schedule it is editing.
        $this->departures = [$editing && ($d = $trip->departure)
            ? ['starts_at' => $d->starts_at->toDateString(), 'ends_at' => $d->ends_at?->toDateString() ?: '', 'capacity' => $d->capacity]
            : ['starts_at' => '', 'ends_at' => '', 'capacity' => 12]];
    }

    // ── collapsed-card summaries ────────────────────────────────────
    // Each section of the form collapses to one line. These produce that line, so a
    // host can see what a closed card holds without opening it.

    /**
     * One stop's trail values, always complete. Setting a single field (a wire:model
     * write, or a test) leaves a partial array behind, so every read merges the blanks
     * back in rather than trusting the keys to be there.
     *
     * @return array<string, mixed>
     */
    public function hikeFor(int $placeId): array
    {
        return array_merge(self::BLANK_HIKE, $this->hikes[$placeId] ?? []);
    }

    /** How many of one stop's trail fields carry a value. Drives that card's badge. */
    public function trailFilledCount(int $placeId): int
    {
        return collect($this->hikeFor($placeId))
            ->filter(fn ($v) => is_array($v) ? $v !== [] : filled($v))
            ->count();
    }

    public function departureSummary(): string
    {
        $dated = collect($this->departures)->filter(fn ($d) => filled($d['starts_at'] ?? null));

        if ($dated->isEmpty()) {
            return 'No dates yet — at least one is required';
        }

        return $dated->count().' '.Str::plural('date', $dated->count())
            .' · from '.Carbon::parse($dated->first()['starts_at'])->format('j M Y');
    }

    public function meetupSummary(): string
    {
        $named = collect($this->meetups)->filter(fn ($m) => filled($m['label'] ?? null));

        return $named->isEmpty()
            ? 'No pickup point set'
            : $named->map(fn ($m) => trim($m['label'].' '.($m['time'] ? '· '.$m['time'] : '')))->join(' · ');
    }

    public function dropoffSummary(): string
    {
        $named = collect($this->dropoffs)->filter(fn ($d) => filled($d['label'] ?? null));

        return $named->isEmpty()
            ? 'Not set — joiners won\'t know when they get back'
            : $named->map(fn ($d) => trim($d['label'].' '.($d['time'] ? '· '.$d['time'] : '')))->join(' · ');
    }

    /** Step 1 summary line: how long the whole itinerary runs. */
    public function lengthSummary(): string
    {
        $days = collect($this->destinations)->sum(fn ($d) => count($d['days'] ?? []));

        return $days.' '.Str::plural('day', $days);
    }

    public function render()
    {
        return view('livewire.host.trips.create', [
            'places' => Place::orderBy('name')->get(),
            'presets' => Auth::user()->meetupPresets()->get(),
            'pastTrips' => Trip::collapseSchedules(
                // a taken-down trip is nothing to start a new one from
                Trip::where('host_id', Auth::id())->where('status', '!=', 'archived')->with('departures')->latest()->take(24)->get()
            )->take(4),
            'takenDown' => $this->isEditing && Trip::find($this->editingId)?->isTakenDown(),
        ])->layout('components.layouts.host', ['title' => 'Tarana · New trip']);
    }

    /**
     * The hours a term is stored with, or null when it is not expressed that way.
     *
     * A down payment deadline on a trip that takes no down payment is meaningless, so it is
     * dropped rather than saved — otherwise switching a trip to pay-in-full would leave a stale
     * deadline behind, invisible in the form and still stamped onto every new booking.
     */
    private function termHours(string $key): ?int
    {
        if ($key === 'downpayment' && $this->deposit === '') {
            return null;
        }

        $mode = $key === 'downpayment' ? $this->downpaymentDueMode : $this->fullDueMode;
        $value = $key === 'downpayment' ? $this->downpaymentDueHours : $this->fullDueHours;

        return $mode === 'relative' && $value !== '' ? (int) $value : null;
    }

    /** The fixed date a term is pinned to, or null. Same drop rule as termHours(). */
    private function termDate(string $key): ?string
    {
        if ($key === 'downpayment' && $this->deposit === '') {
            return null;
        }

        $mode = $key === 'downpayment' ? $this->downpaymentDueMode : $this->fullDueMode;
        $value = $key === 'downpayment' ? $this->downpaymentDueDate : $this->fullDueDate;

        // End of day: a deadline of "1 September" that bites at midnight on the 1st gives the
        // joiner none of the day they were told they had.
        return $mode === 'fixed' && $value !== ''
            ? Carbon::parse($value)->endOfDay()->toDateTimeString()
            : null;
    }
}
