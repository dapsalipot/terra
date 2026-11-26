<?php

namespace App\Http\Controllers;

use App\Support\Qr;
use Illuminate\Support\Facades\Auth;

/**
 * The QR of the signed-in host's own page, the one at /{handle}.
 *
 * A page with no handle has no address to encode, so there is nothing to serve until they claim one.
 */
class HostPageQrController extends Controller
{
    public function __invoke()
    {
        $host = Auth::user();
        abort_unless(filled($host->handle), 404);

        $png = Qr::png(route('hosts.handle', $host));

        return response($png->getString(), 200, [
            'Content-Type' => $png->getMimeType(),
            'Content-Disposition' => 'inline; filename="tarana-'.$host->handle.'-qr.png"',
        ]);
    }
}
