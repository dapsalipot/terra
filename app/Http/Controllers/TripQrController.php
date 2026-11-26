<?php

namespace App\Http\Controllers;

use App\Models\Trip;
use App\Support\Qr;

class TripQrController extends Controller
{
    public function __invoke(Trip $trip)
    {
        abort_unless($trip->status === 'published', 404);

        $png = Qr::png(route('trips.show', $trip));

        return response($png->getString(), 200, [
            'Content-Type' => $png->getMimeType(),
            'Content-Disposition' => 'inline; filename="tarana-'.$trip->slug.'-qr.png"',
        ]);
    }
}
