<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Message extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['pinned' => 'boolean'];
    }

    public function departure()
    {
        return $this->belongsTo(TripDeparture::class, 'trip_departure_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
