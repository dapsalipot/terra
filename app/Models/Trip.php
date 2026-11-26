<?php

namespace App\Models;

use App\Support\StockPhotos;
use App\Support\Visibility;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Trip extends Model
{
    use HasFactory, HasSlug;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'inclusions' => 'array',
            'packing_items' => 'array',
            'requires_id' => 'boolean',
            'price' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'downpayment_due_at' => 'datetime',
            'full_payment_due_at' => 'datetime',
            'public_at' => 'datetime',
        ];
    }

    public function getSlugOptions(): SlugOptions
    {
        // Only the seed. `refreshSlug()` replaces it with the descriptive form once the
        // schedule's date is known, which is after the trip row itself exists.
        return SlugOptions::create()
            ->generateSlugsFrom('title')
            ->saveSlugsTo('slug')
            ->doNotGenerateSlugsOnUpdate();
    }

    /**
     * Rebuild the slug from what a searcher would actually type.
     *
     * One trip per schedule means several rows share a title, and Spatie resolves that
     * with "-2", "-3" — meaningless to a reader and to a search engine. The date is the
     * thing that distinguishes them, so the date goes in the URL:
     *
     *     mount-pulag-2-day-hike-aug-2026
     *
     * First publish only, once the departure is written. Never call it on a trip that has been
     * published: its address is on shared links and printed QR codes, and nothing redirects an
     * old one.
     */
    public function refreshSlug(): void
    {
        $date = $this->departures()->min('starts_at');

        $base = Str::slug(
            $this->title.($date ? ' '.Carbon::parse($date)->format('M Y') : '')
        );

        if ($base === '') {
            return;
        }

        $slug = $base;
        $n = 1;

        while (static::where('slug', $slug)->whereKeyNot($this->getKey())->exists()) {
            $slug = $base.'-'.++$n;
        }

        if ($slug !== $this->slug) {
            $this->forceFill(['slug' => $slug])->save();
        }
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    public function departures()
    {
        return $this->hasMany(TripDeparture::class);
    }

    /** Pickup points only — drop-offs live in the same table under a different kind. */
    public function meetups()
    {
        return $this->hasMany(TripMeetup::class)->where('kind', 'pickup')->orderBy('position');
    }

    public function dropoffs()
    {
        return $this->hasMany(TripMeetup::class)->where('kind', 'dropoff')->orderBy('position');
    }

    public function priceTiers()
    {
        return $this->hasMany(TripPriceTier::class)->orderBy('position');
    }

    /**
     * The tier in force right now, if any. First match wins, so a host who overlaps two
     * windows gets the one they ordered first rather than an arbitrary pick.
     */
    public function currentTier(): ?TripPriceTier
    {
        return $this->priceTiers->first(fn ($t) => $t->coversDate());
    }

    /** What a joiner pays today: the live tier, else the trip's own price. */
    public function getCurrentPriceAttribute(): float
    {
        return (float) ($this->currentTier()?->amount ?? $this->price);
    }

    /** The next tier that takes over, so the page can say "then ₱2,500 from 2 Feb". */
    public function nextTier(): ?TripPriceTier
    {
        $current = $this->currentTier();

        return $this->priceTiers
            ->filter(fn ($t) => $t->starts_at && $t->starts_at->isFuture())
            ->reject(fn ($t) => $current && $t->is($current))
            ->sortBy('starts_at')
            ->first();
    }

    /** What must be paid to hold a place — the deposit if set, otherwise the whole price. */
    public function getPayableNowAttribute(): float
    {
        return (float) ($this->deposit_amount ?? $this->current_price);
    }

    public function takesDeposit(): bool
    {
        return $this->deposit_amount !== null && (float) $this->deposit_amount < $this->current_price;
    }

    /**
     * Collapse a set of trips to one row per offering.
     *
     * Since a trip is now a single schedule, a listing that does not do this shows
     * "Mount Pulag 2 Day Hike" once per date. The soonest-departing schedule represents
     * the group, and each survivor carries `schedule_count` so a card can say how many
     * other dates are behind it.
     *
     * @param  Collection<int, static>  $trips
     * @return Collection<int, static>
     */
    public static function collapseSchedules($trips)
    {
        $key = fn ($t) => $t->trip_group_id ?: 'solo-'.$t->id;
        $counts = $trips->groupBy($key)->map->count();

        return $trips
            // soonest first, so the survivor is the next one a joiner could actually book
            ->sortBy(fn ($t) => optional($t->departures->min('starts_at'))->timestamp ?? PHP_INT_MAX)
            ->unique($key)
            ->each(fn ($t) => $t->schedule_count = $counts[$key($t)] ?? 1)
            ->values();
    }

    /** Schedules published alongside this one, this trip included. */
    public function groupSiblings()
    {
        return $this->trip_group_id
            ? static::where('trip_group_id', $this->trip_group_id)
            : static::whereKey($this->id);
    }

    /** The single departure this trip runs. One trip, one schedule. */
    public function departure()
    {
        return $this->hasOne(TripDeparture::class)->oldest('starts_at');
    }

    /** Total days across the itinerary — what a joiner actually books time off for. */
    public function getDayCountAttribute(): int
    {
        return $this->destinations->sum(fn ($d) => count($d->days ?? []));
    }

    public function destinations()
    {
        return $this->hasMany(TripDestination::class)->orderBy('position');
    }

    public function photos()
    {
        return $this->hasMany(TripPhoto::class)->orderBy('position');
    }

    /** Every stop's trail detail, in itinerary order. A traverse has one per summit. */
    public function hikeDetails()
    {
        return $this->hasManyThrough(
            TripHikeDetail::class,
            TripDestination::class,
            'trip_id',
            'trip_destination_id',
        );
    }

    /** Whether any destination on this trip is a hike — gates the trail panel and wizard step. */
    public function isHike(): bool
    {
        return $this->destinations->contains(fn ($d) => $d->place?->category === 'hike')
            || $this->place?->category === 'hike';
    }

    /**
     * Replace this trip's itinerary. `trips.place_id` is kept in sync with the first stop
     * so the ~20 existing read sites that still query place_id keep working unchanged.
     *
     * @param  array<int, array{place_id: int, days: list<string>}>  $rows
     */
    public function syncDestinations(array $rows): void
    {
        $this->destinations()->delete();

        foreach (array_values($rows) as $i => $row) {
            $this->destinations()->create([
                'place_id' => $row['place_id'],
                'days' => array_values($row['days'] ?? []),
                'position' => $i,
            ]);
        }

        if ($first = $rows[0]['place_id'] ?? null) {
            $this->forceFill(['place_id' => $first])->save();
        }
    }

    /** Trip-level difficulty when set, otherwise the first destination's. */
    public function getEffectiveDifficultyAttribute(): ?string
    {
        return $this->difficulty ?: $this->destinations->first()?->place?->difficulty;
    }

    public function inclusionItems()
    {
        return $this->hasMany(TripInclusion::class)->orderBy('position');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable')->latest();
    }

    public function openDepartures()
    {
        return $this->departures()
            ->where('status', 'open')
            ->where('starts_at', '>', now())
            ->orderBy('starts_at');
    }

    public function getCoverUrlAttribute(): ?string
    {
        if ($this->cover_photo) {
            return Storage::disk('public')->url($this->cover_photo);
        }

        // Stock-photo fallback keyed to the place category until the host uploads their own.
        return StockPhotos::cover($this);
    }

    /** Moderation took it down. Only ModerationService archives a trip, and a host has no way to. */
    public function isTakenDown(): bool
    {
        return $this->status === 'archived';
    }

    public function scopePublished($q)
    {
        /*
         * Suspension takes a host's trips out of discovery without touching the bookings already
         * made on them — those joiners still need their pages, their passes and their host's
         * payment details. Enforced on the scope rather than at each call site so a new listing
         * cannot forget it.
         */
        return $q->where('status', 'published')
            ->whereHas('host', fn ($h) => $h->whereNull('suspended_at'));
    }

    /** What anyone can find in a listing right now, a guest included. */
    public function scopeListed($q)
    {
        return $this->scopeListedFor($q, null);
    }

    /**
     * What `$viewer` can find in a listing: everything `listed()` shows, plus the followers-first trips of
     * the hosts they follow while the window is still open.
     *
     * Listing control only. It decides who is shown a trip, never who may open one. Every column is
     * qualified because Home joins `places`. `public_at` is compared with `<=`, so a window that opens at
     * this very second is already open, and a followers-first trip with no `public_at` never reaches
     * anyone but followers (a NULL comparison is not true).
     */
    public function scopeListedFor($q, ?User $viewer)
    {
        return $q->published()->where(fn ($v) => $v
            ->where('trips.visibility', Visibility::PUBLIC)
            ->orWhere(fn ($f) => $f->where('trips.visibility', Visibility::FOLLOWERS)->where(fn ($w) => $w
                ->where('trips.public_at', '<=', now())
                ->when($viewer, fn ($w) => $w->orWhereIn('trips.host_id',
                    DB::table('follows')->select('host_id')->where('follower_id', $viewer->id))))));
    }

    /**
     * What listings do with this trip right now. A followers-first window that has passed reads as public
     * without the stored value being touched, so no scheduled job is needed to open it.
     */
    public function effectiveVisibility(): string
    {
        $stored = $this->visibility ?? Visibility::PUBLIC;

        return $stored === Visibility::FOLLOWERS && $this->public_at && ! $this->public_at->isFuture()
            ? Visibility::PUBLIC
            : $stored;
    }
}
