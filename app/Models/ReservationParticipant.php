<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationParticipant extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['birthday' => 'date', 'is_booker' => 'boolean'];
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    /** First/middle/last with blanks collapsed, so "Ada  Reyes" never renders. */
    public function fullName(): string
    {
        return trim(implode(' ', array_filter([
            $this->first_name, $this->middle_name, $this->last_name,
        ])));
    }

    /**
     * Enough detail for a permit or manifest.
     *
     * Gender is deliberately excluded: it is nudged in the form but never forced, and a
     * booking must not be blocked on it.
     */
    public function isComplete(): bool
    {
        return filled($this->first_name) && filled($this->last_name) && filled($this->birthday);
    }

    /**
     * Under 18 on the given date — normally the departure date, not today.
     *
     * A 17-year-old who turns 18 before the trip is not a minor for permit purposes, so
     * asking "is this person a minor?" without a date is the wrong question.
     */
    public function isMinorAt(CarbonInterface $date): bool
    {
        return $this->birthday !== null && $this->birthday->diffInYears($date) < 18;
    }
}
