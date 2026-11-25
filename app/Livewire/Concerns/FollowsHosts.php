<?php

namespace App\Livewire\Concerns;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * The Follow button on a host card, for any page that shows a row of them.
 *
 * Follow is the one thing a joiner can do from a card without leaving the page, and it is how a host's
 * audience becomes theirs: a follower hears about the next departure. A guest is sent to log in first.
 */
trait FollowsHosts
{
    public function toggleFollow(int $hostId): void
    {
        if (! Auth::check()) {
            $this->redirectRoute('login', navigate: true);

            return;
        }

        $host = User::where('role', 'host')->findOrFail($hostId);
        $me = Auth::user();

        $me->isFollowing($host) ? $me->unfollow($host) : $me->follow($host);
    }
}
