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
            'amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /**
     * The travellers this payment settles.
     *
     * Empty means the whole booking, which is what every submission made before per-participant
     * payment existed means — and what a host still gets when they accept without narrowing it.
     */
    public function participants()
    {
        return $this->belongsToMany(ReservationParticipant::class, 'participant_payment_submission');
    }

    public function reservation()
    {
        return $this->belongsTo(Reservation::class);
    }
}
