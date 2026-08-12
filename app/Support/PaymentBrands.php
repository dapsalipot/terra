<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Recognisable branding for the payment methods a Philippine host actually uses.
 *
 * Hosts type their own labels ("Gcash", "GCash", "G-Cash", "BPI Savings"), so this matches on a
 * normalised form rather than an exact string and falls back to a neutral mark. Matching is by
 * longest key first, so "unionbank" is not swallowed by a shorter "union".
 *
 * NOTE ON THE MARKS: these are wordmark tiles in each provider's brand colour, not the official
 * logo files. They read correctly at a glance and carry no licensing question. Dropping real SVG
 * assets in later is a change to this one file — give each entry a `svg` and render that instead
 * of the initials.
 */
class PaymentBrands
{
    /** key => [display label, brand colour, foreground on that colour, short mark] */
    public const CATALOG = [
        'gcash' => ['GCash', '#007DFE', '#FFFFFF', 'G'],
        'maya' => ['Maya', '#00C08B', '#04231A', 'M'],
        'paymaya' => ['Maya', '#00C08B', '#04231A', 'M'],
        'grabpay' => ['GrabPay', '#00B14F', '#FFFFFF', 'G'],
        'coins' => ['Coins.ph', '#1652F0', '#FFFFFF', 'C'],
        'seabank' => ['SeaBank', '#F4732A', '#FFFFFF', 'S'],
        'gotyme' => ['GoTyme', '#111111', '#00D1C1', 'GT'],
        'unionbank' => ['UnionBank', '#FF6600', '#FFFFFF', 'UB'],
        'bpi' => ['BPI', '#A31F34', '#FFFFFF', 'BPI'],
        'bdo' => ['BDO', '#002F6C', '#FFFFFF', 'BDO'],
        'metrobank' => ['Metrobank', '#003876', '#FFFFFF', 'MB'],
        'landbank' => ['Landbank', '#007A33', '#FFFFFF', 'LB'],
        'security bank' => ['Security Bank', '#00953B', '#FFFFFF', 'SB'],
        'chinabank' => ['China Bank', '#C8102E', '#FFFFFF', 'CB'],
        'rcbc' => ['RCBC', '#00539B', '#FFFFFF', 'RCBC'],
        'palawan' => ['Palawan Express', '#0B4EA2', '#FFFFFF', 'PE'],
        'cebuana' => ['Cebuana Lhuillier', '#E4002B', '#FFFFFF', 'CL'],
        // Wise's bright green on its forest ink, not the reverse: light-on-dark at this size
        // was the least legible tile on the page.
        'wise' => ['Wise', '#9FE870', '#163300', 'W'],
        'paypal' => ['PayPal', '#003087', '#FFFFFF', 'PP'],
        'revolut' => ['Revolut', '#0666EB', '#FFFFFF', 'R'],
        'cash' => ['Cash', '#5E6B44', '#FFFFFF', '₱'],
        'bank' => ['Bank transfer', '#2C3826', '#FFFFFF', 'BT'],
    ];

    /** The neutral tile for a label nothing in the catalog matches. */
    private const FALLBACK = ['#2C3826', '#FFFFFF'];

    /**
     * Brand a host-written label.
     *
     * @return array{key: ?string, label: string, color: string, fg: string, mark: string}
     */
    public static function resolve(?string $label): array
    {
        $label = trim((string) $label);
        $needle = Str::of($label)->lower()->replaceMatches('/[^a-z0-9 ]/', '')->squish()->toString();

        // Longest key first: "bank" must not claim "unionbank", and "maya" must not claim
        // "paymaya" before paymaya's own entry is considered.
        $keys = array_keys(self::CATALOG);
        usort($keys, fn ($a, $b) => strlen($b) <=> strlen($a));

        foreach ($keys as $key) {
            if ($needle !== '' && str_contains($needle, $key)) {
                [$brand, $color, $fg, $mark] = self::CATALOG[$key];

                return [
                    'key' => $key,
                    // The host's own wording wins when they wrote something more specific than
                    // the brand name — "BPI Savings" is more use to a joiner than "BPI".
                    'label' => $label !== '' ? $label : $brand,
                    'color' => $color,
                    'fg' => $fg,
                    'mark' => $mark,
                ];
            }
        }

        return [
            'key' => null,
            'label' => $label !== '' ? $label : 'Payment',
            'color' => self::FALLBACK[0],
            'fg' => self::FALLBACK[1],
            'mark' => strtoupper(mb_substr($label ?: '?', 0, 1)),
        ];
    }
}
