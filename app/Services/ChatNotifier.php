<?php

namespace App\Services;

use App\Mail\ChatActivityMail;
use App\Models\Message;
use App\Models\TripDeparture;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Tells a trip group that their chat has moved, without becoming an email per message.
 *
 * A group chat before a trip is bursty — twenty messages in ten minutes the night before, then
 * nothing for a week. Mailing each one is how a useful notification becomes a filter rule, so
 * each person is told at most once per quiet period and the mail carries no message text: it
 * says the conversation moved and links to it.
 */
class ChatNotifier
{
    /** How long a person stays "already told" for one departure. */
    private const QUIET_MINUTES = 60;

    public function announce(Message $message): int
    {
        $departure = $message->departure ?? TripDeparture::find($message->trip_departure_id);

        if (! $departure) {
            return 0;
        }

        $sent = 0;

        foreach ($this->audience($departure, (int) $message->user_id) as $user) {
            $key = "chat-told:{$departure->id}:{$user->id}";

            // Cache rather than a column: this is a rate limit on a notification, not a fact
            // about the booking, and it should expire on its own rather than need cleaning up.
            if (Cache::has($key)) {
                continue;
            }

            try {
                Mail::to($user->email)->send(new ChatActivityMail($departure, $user));
            } catch (\Throwable $e) {
                report($e);

                continue;
            }

            Cache::put($key, true, now()->addMinutes(self::QUIET_MINUTES));
            $sent++;
        }

        return $sent;
    }

    /**
     * Everyone entitled to read the chat, minus whoever just spoke.
     *
     * The same membership the gate enforces — confirmed joiners and the host — so nobody is
     * emailed about a room they would be refused entry to.
     */
    private function audience(TripDeparture $departure, int $senderId)
    {
        $host = $departure->trip?->host;

        $joiners = User::query()
            ->whereIn('id', $departure->reservations()
                ->where('status', 'confirmed')
                ->whereNotNull('joiner_id')
                ->pluck('joiner_id'))
            ->get();

        return $joiners
            ->when($host, fn ($c) => $c->push($host))
            ->unique('id')
            ->reject(fn (User $u) => $u->id === $senderId || blank($u->email))
            ->values();
    }
}
