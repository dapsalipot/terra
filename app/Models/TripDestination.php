<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripDestination extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['days' => 'array'];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function place()
    {
        return $this->belongsTo(Place::class);
    }

    /** Trail detail for this stop. Only hikes have one. */
    public function hikeDetail()
    {
        return $this->hasOne(TripHikeDetail::class);
    }

    public function isHike(): bool
    {
        return $this->place?->category === 'hike';
    }
}
