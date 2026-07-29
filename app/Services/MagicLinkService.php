<?php

namespace App\Services;

use App\Models\MagicLink;
use Illuminate\Support\Str;

class MagicLinkService
{
    /**
     * Mint a single-use link.
     *
     * The raw token is returned here and never again — only its hash is stored, so a leaked
     * database yields no usable links. Callers that need to show or email the URL must do it
     * with this return value.
     *
     * @return array{link: MagicLink, url: string}
     */
    public function issue(string $purpose, array $attrs, int $ttlMinutes = 30): array
    {
        $raw = Str::random(64);

        $link = MagicLink::create([
            'token' => hash('sha256', $raw),
            'purpose' => $purpose,
            'user_id' => $attrs['user_id'] ?? null,
            'email' => $attrs['email'] ?? null,
            'reservation_id' => $attrs['reservation_id'] ?? null,
            'expires_at' => now()->addMinutes($ttlMinutes),
        ]);

        return ['link' => $link, 'url' => route('magic.consume', ['token' => $raw])];
    }

    /**
     * Redeem a raw token, marking it used.
     *
     * Expired, already-used and never-existed all return null so a caller cannot probe which
     * case it hit — that would leak whether a token was ever real.
     *
     * The redemption itself is a single conditional UPDATE — `WHERE used_at IS NULL AND
     * expires_at > now()` — rather than a read-then-write. That makes the claim atomic: the
     * database applies the write to at most one of two concurrent callers racing the same raw
     * token, so only one can ever get a non-null result back. A separate isValid() check
     * followed by an unconditional write would leave a window where both callers read
     * "unused" before either writes, letting both redeem the same single-use link.
     */
    public function consume(string $rawToken): ?MagicLink
    {
        $hash = hash('sha256', $rawToken);

        $claimed = MagicLink::where('token', $hash)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now()]);

        if ($claimed === 0) {
            return null;
        }

        return MagicLink::where('token', $hash)->first();
    }
}
