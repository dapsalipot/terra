<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PaymentSubmission extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
