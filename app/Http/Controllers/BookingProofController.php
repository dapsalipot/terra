<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use Illuminate\Support\Facades\Storage;

/**
 * The joiner's own view of the receipt they uploaded.
 *
 * Separate from ProofController rather than a widening of it: that route lives behind the host
 * middleware and answers "may this host see this submission?". This one answers a different
 * question — "does the caller hold this booking's token?" — which is the same credential the
 * booking page itself runs on, and it stays out of the host path entirely.
 */
class BookingProofController extends Controller
{
    public function __invoke(string $token)
    {
        $reservation = Reservation::where('public_token', $token)->firstOrFail();

        $payment = $reservation->latestPayment;

        abort_unless(
            $payment?->proof_image && Storage::disk('local')->exists($payment->proof_image),
            404,
        );

        return Storage::disk('local')->response($payment->proof_image);
    }
}
