<?php

namespace App\Models;

use App\Support\DaySpan;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class TripDeparture extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function waitlistEntries()
    {
        return $this->hasMany(WaitlistEntry::class);
    }

    public function reservations()
    {
        return $this->hasMany(Reservation::class);
    }

    /** Seats held against capacity: sum of party sizes in holding states. */
    public function heldCount(): int
    {
        return (int) $this->reservations()
            ->whereIn('status', Reservation::HOLDING)
            ->sum('party_size');
    }

    public function spotsLeft(): int
    {
        return max(0, $this->capacity - $this->heldCount());
    }

    /**
     * Departures touching a window at any point, not only those starting inside it.
     *
     * A calendar that filtered on `starts_at` alone dropped anything that began before the grid
     * and was still running inside it — the middle of a trip that crosses a month boundary was
     * invisible in both months.
     */
    public function scopeOverlapping($query, $from, $to)
    {
        $from = Carbon::parse($from)->startOfDay();
        $to = Carbon::parse($to)->endOfDay();

        return $query
            ->where('starts_at', '<=', $to)
            ->where(function ($q) use ($from) {
                // Either end of the span reaching into the window is enough. Asking only
                // "does ends_at reach the window?" would hide a departure whose end date is
                // absent or, worse, earlier than its start — bad data should show up in the
                // wrong place, not disappear from the calendar entirely.
                $q->where('starts_at', '>=', $from)
                    ->orWhere('ends_at', '>=', $from);
            });
    }

    /** Calendar days this departure occupies. A same-day trip is 1, never 0. */
    public function spanDays(): int
    {
        return DaySpan::length($this->starts_at, $this->ends_at);
    }

    public function isMultiDay(): bool
    {
        return $this->spanDays() > 1;
    }

    /**
     * Which day of the trip a given date is, 1-based. 0 when the date is outside the trip.
     *
     * This is what lets a calendar square say "Day 2 of 3" instead of repeating an identical
     * chip three times and reading as three separate departures.
     */
    public function dayNumberOn($date): int
    {
        $target = Carbon::parse($date)->toDateString();
        $index = array_search($target, DaySpan::dates($this->starts_at, $this->ends_at), true);

        return $index === false ? 0 : $index + 1;
    }
}
