<?php

namespace App\Models;

use App\Support\Accents;
use App\Support\Vibes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class HostProfile extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payment_methods' => 'array',
            'links' => 'array',
            'vibes' => 'array',
            'highlights' => 'array',
            'verified_at' => 'datetime',
        ];
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

    public function getAccentTextAttribute(): string
    {
        return Accents::text($this->accent);
    }

    /** The host's vibes as key => label, in the order they chose. */
    public function vibeLabels(): array
    {
        return Vibes::resolve($this->vibes);
    }

    /** Their "why join us" lines, blanks dropped. */
    public function highlightLines(): array
    {
        return array_values(array_filter(array_map('trim', $this->highlights ?? [])));
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
