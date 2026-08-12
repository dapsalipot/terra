<?php

namespace App\Models;

use App\Support\Accents;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        return $this->cover_photo ? Storage::disk('public')->url($this->cover_photo) : null;
    }

    public function getAccentHexAttribute(): string
    {
        return Accents::hex($this->accent);
    }

    public function getAccentSoftAttribute(): string
    {
        return Accents::soft($this->accent);
    }
}
