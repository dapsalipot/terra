<?php

namespace App\Http\Controllers;

use App\Models\PaymentSubmission;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProofController extends Controller
{
    public function __invoke(PaymentSubmission $payment)
    {
        $payment->load('reservation.departure.trip');
        $hostId = $payment->reservation?->departure?->trip?->host_id;

        abort_unless($hostId === Auth::id(), 403);
        abort_unless($payment->proof_image && Storage::disk('local')->exists($payment->proof_image), 404);

        return Storage::disk('local')->response($payment->proof_image);
    }
}
