<?php

namespace App\Http\Controllers;

use App\Models\ReservationParticipant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/**
 * A traveller's ID photo, served only to the two parties entitled to it.
 *
 * The host, because they are the one checking names at a permit desk; and whoever holds the
 * booking's token, because that is the credential the booking itself runs on. Nobody else — an
 * ID is the most sensitive thing this app stores, and it lives on the private disk precisely so
 * that a URL alone is never enough.
 */
class ParticipantIdController extends Controller
{
    public function __invoke(ReservationParticipant $participant)
    {
        $reservation = $participant->reservation;
        $trip = $reservation?->departure?->trip;

        $isHost = $trip && $trip->host_id === Auth::id();
        $isJoiner = $reservation?->joiner_id && $reservation->joiner_id === Auth::id();

        $token = (string) request()->query('token');
        $holdsToken = $reservation && $token !== '' && hash_equals($reservation->public_token, $token);

        abort_unless($isHost || $isJoiner || $holdsToken, 403);
        abort_unless(
            $participant->id_image && Storage::disk('local')->exists($participant->id_image),
            404,
        );

        return Storage::disk('local')->response($participant->id_image);
    }
}
