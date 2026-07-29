<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TripPhoto extends Model
{
    protected $guarded = [];

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
