<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MagicLink extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'used_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }

    public function isValid(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }

    public function consume(): void
    {
        $this->update(['used_at' => now()]);
    }
}
