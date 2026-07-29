<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TripMeetup extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['lat' => 'float', 'lng' => 'float'];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? Storage::disk('public')->url($this->image) : null;
    }

    /**
     * "18:30", whatever the driver stored. MySQL's TIME column reads back as 18:30:00
     * while SQLite keeps the raw 18:30 it was given, so never render `time` directly.
     */
    public function getTimeLabelAttribute(): ?string
    {
        return $this->time ? substr($this->time, 0, 5) : null;
    }

    public function hasPin(): bool
    {
        return $this->lat !== null && $this->lng !== null;
    }

    /**
     * Google Maps embed. The `output=embed` form needs no API key, which keeps the app
     * deployable without credentials; swap for the official Embed API if you add a key.
     */
    public function getMapEmbedUrlAttribute(): ?string
    {
        if (! $this->hasPin()) {
            return null;
        }

        return 'https://maps.google.com/maps?q='.$this->lat.','.$this->lng.'&z=15&output=embed';
    }

    /** Deep link that opens the native Maps app on mobile. */
    public function getMapLinkAttribute(): ?string
    {
        if (! $this->hasPin()) {
            return $this->address ? 'https://www.google.com/maps/search/?api=1&query='.urlencode($this->address) : null;
        }

        return 'https://www.google.com/maps/search/?api=1&query='.$this->lat.','.$this->lng;
    }
}
