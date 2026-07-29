<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

class TripQrController extends Controller
{
    public function __invoke(Trip $trip)
    {
        abort_unless($trip->status === 'published', 404);

        $result = new Builder(
            writer: new PngWriter(),
            data: route('trips.show', $trip),
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 600,
            margin: 24,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new \Endroid\QrCode\Color\Color(44, 56, 38),   // pine
            backgroundColor: new \Endroid\QrCode\Color\Color(255, 255, 255),
        );

        $png = $result->build();

        return response($png->getString(), 200, [
            'Content-Type' => $png->getMimeType(),
            'Content-Disposition' => 'inline; filename="peeps-'.$trip->slug.'-qr.png"',
        ]);
    }
}
