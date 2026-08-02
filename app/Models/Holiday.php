<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    /**
     * Inclusive of both bounds.
     *
     * whereBetween compares strings, and the `date` cast serialises to `Y-m-d H:i:s`, so a stored
     * `2026-08-31 00:00:00` sorts after the bound `2026-08-31` and drops out. That silently lost
     * any holiday falling on the last day of a range — including 31 December from forYear(), and
     * the final cell of a calendar grid. whereDate compares the date part on both sides.
     */
    /**
     * Exact-date lookup.
     *
     * A bare where('date', '2026-06-12') never matches: the cast serialises to `Y-m-d H:i:s`, so
     * the stored value is `2026-06-12 00:00:00` and the string comparison fails. That silently
     * broke the import's manual-override guard — the existing row was never found, so a
     * hand-corrected holiday would have been overwritten on the next run.
     */
    public function scopeOnDate($query, string $date)
    {
        return $query->whereDate('date', $date);
    }

    public function scopeBetween($query, string $from, string $to)
    {
        return $query->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);
    }
}
