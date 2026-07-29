<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HostMeetupPreset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }
}
