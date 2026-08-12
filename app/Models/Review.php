<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    /**
     * Only the reviews the public may see.
     *
     * Moderation that hides a review from a listing but leaves it in the average would be
     * cosmetic: the number it moved is the number people actually read.
     */
    /**
     * The host this review is about, whoever they are.
     *
     * Reviews are polymorphic — a trip, a place or a host — and only two of those have someone
     * with standing to answer. A place has nobody, so nobody may reply to one.
     */
    public function repliableBy(): ?User
    {
        return match ($this->reviewable_type) {
            Trip::class => $this->reviewable?->host,
            User::class => $this->reviewable,
            default => null,
        };
    }

    public function hasReply(): bool
    {
        return filled($this->reply_body) && $this->replied_at !== null;
    }

    public function scopeVisible($q)
    {
        return $q->whereNull('hidden_at');
    }

    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['replied_at' => 'datetime'];
    }

    public function reviewable()
    {
        return $this->morphTo();
    }

    public function joiner()
    {
        return $this->belongsTo(User::class, 'joiner_id');
    }
}
