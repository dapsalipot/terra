<?php

namespace App\Livewire\Joiner;

use App\Mail\MagicLinkMail;
use Carbon\CarbonInterface;
use App\Models\Reservation;
use App\Models\Review;
use App\Models\Trip;
use App\Services\MagicLinkService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Livewire\Component;

class Dashboard extends Component
{
    public bool $showClaim = false;
    public ?string $claimStatus = null;

    /**
     * Email an attach link for any guest bookings made with this account's address.
     *
     * The link is emailed rather than acted on directly: someone signed in could otherwise claim
     * a booking simply by having typed the right address into a guest registration form. Clicking
     * a link we sent to the mailbox is what proves control of it.
     */
    public function claimGuestBookings(MagicLinkService $links): void
    {
        $user = Auth::user();

        $orphan = Reservation::whereNull('joiner_id')
            ->where('contact_email', $user->email)
            ->whereNotIn('status', ['cancelled'])
            ->first();

        // The response is identical either way, so this cannot be used to probe which addresses
        // have guest bookings against them.
        $this->claimStatus = 'If any bookings were made with '.$user->email.', we have emailed a link to attach them.';
        $this->showClaim = false;

        if (! $orphan) {
            return;
        }

        ['url' => $url] = $links->issue('attach', [
            'user_id' => $user->id,
            'email' => $user->email,
            'reservation_id' => $orphan->id,
        ]);

        try {
            Mail::to($user->email)->send(new MagicLinkMail($url, $user->full_name));
        } catch (\Throwable $e) {
            report($e);
        }
    }

    /**
     * How long the seat is held.
     *
     * An absolute diff, because diffForHumans() alone produces "held until 5 hours from now".
     * Expired holds get their own wording: the sweep that cancels them runs on a schedule, so
     * there is a window where a hold is past its time but still listed, and telling someone their
     * seat is "held until 5 hours ago" is worse than telling them it lapsed.
     */
    private function holdWording(Reservation $r): string
    {
        if (! $r->expires_at) {
            return 'Your host is waiting on payment';
        }

        if ($r->expires_at->isPast()) {
            return 'This hold has lapsed — pay now to see if the seat is still free';
        }

        return 'Your seat is held for another '
            .$r->expires_at->diffForHumans(['syntax' => CarbonInterface::DIFF_ABSOLUTE]);
    }

    /**
     * The things that cost the joiner something if ignored, in the order they bite.
     *
     * An unpaid hold expires and releases the seat, so it leads. Missing traveller details block
     * the host's permits. A balance is money owed. A review is the only item that is purely
     * optional, so it sits last.
     *
     * @param  Collection<int, Reservation>  $reservations
     */
    private function attention(Collection $reservations, Collection $reviewedTripIds): array
    {
        $items = [];

        foreach ($reservations as $r) {
            $trip = $r->departure?->trip;

            if (! $trip) {
                continue;
            }

            $link = route('reservations.show', ['token' => $r->public_token]);

            if (in_array($r->status, ['pending', 'awaiting_payment'], true)) {
                $items[] = [
                    'kind' => 'pay',
                    'urgent' => true,
                    'title' => 'Pay for '.$trip->title,
                    'detail' => $this->holdWording($r),
                    'cta' => 'Pay now',
                    'href' => $link,
                ];

                continue;   // paying comes before anything else on the same booking
            }

            if ($r->status === 'confirmed') {
                $missing = $r->incompleteParticipants();

                if ($missing->isNotEmpty()) {
                    $items[] = [
                        'kind' => 'details',
                        'urgent' => false,
                        'title' => $missing->count().' of '.$r->participants->count().' travellers still need details',
                        'detail' => $trip->title.' — your host needs each name and birthday for permits',
                        'cta' => 'Add them',
                        'href' => $link,
                    ];
                }

                if ($r->owesBalance()) {
                    $money = $r->money();
                    $items[] = [
                        'kind' => 'balance',
                        'urgent' => false,
                        'title' => 'Balance due on '.$trip->title,
                        'detail' => number_format($money['due']).' to settle with your host on the day',
                        'cta' => 'See booking',
                        'href' => $link,
                    ];
                }
            }
        }

        // Reviews, once the trip has actually run.
        foreach ($reservations as $r) {
            $trip = $r->departure?->trip;

            if (! $trip || $r->status !== 'confirmed' || $r->departure->status !== 'completed') {
                continue;
            }

            if ($reviewedTripIds->contains($trip->id)) {
                continue;
            }

            $items[] = [
                'kind' => 'review',
                'urgent' => false,
                'title' => 'How was '.$trip->title.'?',
                'detail' => 'Your review helps the next group choose',
                'cta' => 'Leave a review',
                'href' => route('trips.show', $trip),
            ];
        }

        // Urgent first, order otherwise preserved.
        usort($items, fn ($a, $b) => ($b['urgent'] <=> $a['urgent']));

        return $items;
    }

    public function render()
    {
        $user = Auth::user();

        $reservations = Reservation::where('joiner_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->with(['departure.trip.host', 'participants'])
            ->get()
            ->filter(fn (Reservation $r) => $r->departure !== null)
            ->sortBy(fn (Reservation $r) => $r->departure->starts_at)
            ->values();

        [$past, $upcoming] = $reservations->partition(
            fn (Reservation $r) => $r->departure->starts_at->isPast()
        );

        $reviewedTripIds = Review::where('joiner_id', $user->id)
            ->where('reviewable_type', Trip::class)
            ->pluck('reviewable_id');

        return view('livewire.joiner.dashboard', [
            'attention' => $this->attention($reservations, $reviewedTripIds),
            'upcoming' => $upcoming->values(),
            'past' => $past->sortByDesc(fn (Reservation $r) => $r->departure->starts_at)->values(),
        ])->layout('components.layouts.site');
    }
}
