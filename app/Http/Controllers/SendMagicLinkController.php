<?php

namespace App\Http\Controllers;

use App\Mail\MagicLinkMail;
use App\Models\User;
use App\Services\MagicLinkService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class SendMagicLinkController extends Controller
{
    public function __invoke(Request $request, MagicLinkService $links)
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', $data['email'])->first();

        if ($user) {
            ['url' => $url] = $links->issue('login', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            Mail::to($user->email)->send(new MagicLinkMail($url, $user->full_name));
        }

        // The response is identical either way: saying whether an address is registered would
        // turn this endpoint into an account-enumeration oracle.
        return back()->with('status', 'If that address has a Peeps account, a sign-in link is on its way.');
    }
}
