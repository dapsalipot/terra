<?php

namespace App\Http\Controllers;

use App\Models\ReservationParticipant;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Support\Facades\Auth;

/**
 * The QR square on a boarding pass.
 *
 * It encodes the pass code and nothing else — no URL, no name, no booking id. A code that a
 * scanner has to resolve server-side leaks nothing when someone photographs a stranger's pass,
 * and it is the only thing check-in needs.
 *
 * High error correction on purpose: this gets scanned off a cracked phone screen in daylight at
 * a trailhead, which is the worst case a QR has.
 */
class PassQrController extends Controller
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
        abort_unless((bool) $participant->pass_code, 404);

        $png = (new Builder(
            writer: new PngWriter,
            data: $participant->pass_code,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 720,
            margin: 16,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(31, 28, 21),      // ink, for maximum contrast on white
            backgroundColor: new Color(255, 255, 255),
        ))->build();

        return response($png->getString(), 200, [
            'Content-Type' => $png->getMimeType(),
            'Content-Disposition' => 'inline; filename="tarana-pass-'.$participant->pass_code.'.png"',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
