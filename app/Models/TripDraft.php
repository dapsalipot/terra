<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripDraft extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function host()
    {
        return $this->belongsTo(User::class, 'host_id');
    }
}
