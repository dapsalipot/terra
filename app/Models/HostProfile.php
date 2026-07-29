<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HostProfile extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['payment_methods' => 'array', 'links' => 'array'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function getCoverUrlAttribute(): ?string
    {
        return $this->cover_photo ? \Illuminate\Support\Facades\Storage::disk('public')->url($this->cover_photo) : null;
    }

    public function getAccentHexAttribute(): string
    {
        return \App\Support\Accents::hex($this->accent);
    }

    public function getAccentSoftAttribute(): string
    {
        return \App\Support\Accents::soft($this->accent);
    }
}
