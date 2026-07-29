<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
