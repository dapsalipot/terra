<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function images()
    {
        return $this->hasMany(PostImage::class)->orderBy('position');
    }

    public function isRecap(): bool
    {
        return $this->type === 'recap';
    }
}
