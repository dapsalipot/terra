<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'role',
        'handle',
        'avatar',
        'suspended_at',
        'suspended_reason',
        'suspended_by',
        'phone',
        'birthday',
        'gender',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** Reviews written *about* this host (polymorphic target). */
    public function reviewsReceived()
    {
        return $this->morphMany(Review::class, 'reviewable')->latest();
    }

    public function meetupPresets()
    {
        return $this->hasMany(HostMeetupPreset::class, 'host_id')->orderBy('label');
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? Storage::disk('public')->url($this->avatar) : null;
    }

    /** Cream-on-pine monogram fallback when a host has not uploaded an avatar. */
    public function getInitialsAttribute(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return strtoupper(mb_substr($parts[0] ?? '', 0, 1).(count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));
    }

    /** Name parts when we have them, falling back to the display name. */
    public function getFullNameAttribute(): string
    {
        $parts = trim(implode(' ', array_filter([
            $this->first_name, $this->middle_name, $this->last_name,
        ])));

        return $parts ?: (string) $this->name;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suspended_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'birthday' => 'date',
        ];
    }

    public function isHost(): bool
    {
        return $this->role === 'host';
    }

    /** The platform's own staff. Deliberately not a host — a moderator is not a seller. */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isSuspended(): bool
    {
        return $this->suspended_at !== null;
    }

    public function hostProfile()
    {
        return $this->hasOne(HostProfile::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class, 'host_id');
    }

    public function posts()
    {
        return $this->hasMany(Post::class, 'host_id')->latest();
    }

    /** Hosts this user follows. */
    public function following()
    {
        return $this->belongsToMany(User::class, 'follows', 'follower_id', 'host_id')->withTimestamps();
    }

    /** Users who follow this host. */
    public function followers()
    {
        return $this->belongsToMany(User::class, 'follows', 'host_id', 'follower_id')->withTimestamps();
    }

    public function follow(User $host): void
    {
        if ($host->id === $this->id || ! $host->isHost()) {
            return;
        }
        $this->following()->syncWithoutDetaching([$host->id]);
        $host->refreshFollowerCount();
    }

    public function unfollow(User $host): void
    {
        $this->following()->detach($host->id);
        $host->refreshFollowerCount();
    }

    public function isFollowing(User $host): bool
    {
        return $this->following()->whereKey($host->id)->exists();
    }

    public function refreshFollowerCount(): void
    {
        $this->hostProfile?->update(['followers_count' => $this->followers()->count()]);
    }
}
