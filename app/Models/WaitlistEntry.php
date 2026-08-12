<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WaitlistEntry extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['notified_at' => 'datetime'];
    }

    public function departure()
    {
        return $this->belongsTo(TripDeparture::class, 'trip_departure_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Still waiting to hear anything. */
    public function scopeWaiting($q)
    {
        return $q->whereNull('notified_at');
    }
}
