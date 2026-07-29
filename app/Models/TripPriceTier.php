<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripPriceTier extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'starts_at' => 'date', 'ends_at' => 'date'];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    /** Is this tier the one in force at $when? Null bounds mean open-ended. */
    public function coversDate(?\Illuminate\Support\Carbon $when = null): bool
    {
        $when = ($when ?? now())->startOfDay();

        return ! ($this->starts_at && $when->lt($this->starts_at))
            && ! ($this->ends_at && $when->gt($this->ends_at));
    }

    /** "until 1 Feb", "from 2 Feb", "1–14 Feb" — whatever bounds it actually has. */
    public function getWindowLabelAttribute(): ?string
    {
        return match (true) {
            (bool) $this->starts_at && (bool) $this->ends_at => $this->starts_at->format('j M').' – '.$this->ends_at->format('j M'),
            (bool) $this->ends_at => 'until '.$this->ends_at->format('j M'),
            (bool) $this->starts_at => 'from '.$this->starts_at->format('j M'),
            default => null,
        };
    }
}
