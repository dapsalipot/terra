<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\ReservationParticipant;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Boarding passes and the door they open.
 *
 * A pass is issued when the host says the money is in, because that is the moment a seat stops
 * being provisional. Codes are random rather than derived from the id: a sequential or guessable
 * code would let anyone walk up with the next number in the series.
 */
class CheckInService
{
    /** Give every named traveller on a fully-paid booking a code, without disturbing existing ones. */
    public function issueFor(Reservation $reservation): int
    {
        if ($reservation->status === 'cancelled') {
            return 0;
        }

        $issued = 0;

        /*
         * A pass per traveller whose share is settled, not per booking.
         *
         * This used to require the whole reservation to be tagged fully paid, which on a group
         * booking held four people's passes hostage to a fifth who had not sent theirs. Payment
         * is recorded per participant now, so the pass follows the money.
         */
        foreach ($reservation->participants as $participant) {
            if ($participant->pass_code || ! $reservation->participantIsPaid($participant)) {
                continue;
            }

            $participant->update(['pass_code' => $this->freshCode()]);
            $issued++;
        }

        return $issued;
    }

    /** Withdraw the passes when the claim they rest on is withdrawn. */
    public function revokeFor(Reservation $reservation): void
    {
        $reservation->participants()->update([
            'pass_code' => null,
            'checked_in_at' => null,
            'checked_in_by' => null,
        ]);
    }

    /**
     * Scan a code at the trailhead.
     *
     * Returns the participant on success. Everything that can go wrong is a distinct exception
     * message rather than a null, because a host holding a phone in the rain needs to know
     * whether this is the wrong trip, an already-scanned pass, or a code from nowhere.
     */
    public function checkIn(string $code, User $host, ?int $departureId = null): ReservationParticipant
    {
        $participant = ReservationParticipant::where('pass_code', trim($code))
            ->with('reservation.departure.trip')
            ->first();

        if (! $participant) {
            throw new \RuntimeException('That pass is not one of ours.');
        }

        $reservation = $participant->reservation;
        $departure = $reservation?->departure;

        if (! $departure || $departure->trip->host_id !== $host->id) {
            throw new \RuntimeException('That pass belongs to another host.');
        }

        if ($departureId && $departure->id !== $departureId) {
            throw new \RuntimeException('That pass is for '.$departure->trip->title.', not this departure.');
        }

        if ($reservation->status === 'cancelled') {
            throw new \RuntimeException('That booking was cancelled.');
        }

        if ($participant->checked_in_at) {
            throw new \RuntimeException($participant->fullName().' was already checked in at '.$participant->checked_in_at->format('g:ia').'.');
        }

        $participant->update(['checked_in_at' => now(), 'checked_in_by' => $host->id]);

        return $participant;
    }

    /**
     * Undo a check-in.
     *
     * Scanning is done one-handed on a phone in weather, and the wrong pass gets scanned. Without
     * a reverse the only remedy was editing the database, and the "already checked in" guard made
     * a second, correct scan impossible.
     */
    public function undoCheckIn(ReservationParticipant $participant, User $host): ReservationParticipant
    {
        $trip = $participant->reservation?->departure?->trip;

        abort_unless($trip && $trip->host_id === $host->id, 403);

        $participant->update(['checked_in_at' => null, 'checked_in_by' => null]);

        return $participant;
    }

    /** 12 characters from an unambiguous alphabet — no O/0 or I/1 to misread aloud or retype. */
    private function freshCode(): string
    {
        do {
            $code = 'TRA-'.Str::upper(Str::password(12, letters: true, numbers: true, symbols: false, spaces: false));
            $code = str_replace(['O', '0', 'I', 'L', '1'], ['X', 'Y', 'Z', 'W', 'V'], $code);
        } while (ReservationParticipant::where('pass_code', $code)->exists());

        return $code;
    }
}
