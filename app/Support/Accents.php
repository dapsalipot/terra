<?php

namespace App\Support;

/**
 * Host profile accents. Hosts pick a *key*, never a raw hex — so every profile stays
 * inside the Trail Earth palette no matter how much a host customises their page.
 *
 * The accent themes atmosphere only (cover wash, tab underline, badges, link colour).
 * Primary buttons remain pine/moss per the design system.
 */
class Accents
{
    /*
     * `hex` is for fills, rules and washes; `text` is for words. Terracotta and amber are 4.2:1 and 3.5:1 on
     * their own tint, which is fine for a swatch and not for a tagline, so they carry a deeper `text` value.
     * The rest already clear 4.5:1 and have none (text() falls back to hex).
     */
    public const PALETTE = [
        'moss' => ['label' => 'Moss', 'hex' => '#5E6B44', 'soft' => '#EDF0E6'],
        'pine' => ['label' => 'Pine', 'hex' => '#2C3826', 'soft' => '#E6EAE3'],
        'terra' => ['label' => 'Terracotta', 'hex' => '#BC4B26', 'soft' => '#F8E7E0', 'text' => '#A94524'],
        'amber' => ['label' => 'Amber', 'hex' => '#A8761F', 'soft' => '#F8EEDC', 'text' => '#87601D'],
        'clay' => ['label' => 'Clay', 'hex' => '#8C5A3C', 'soft' => '#F3E8E0', 'text' => '#8A593B'],
        'slate' => ['label' => 'Slate', 'hex' => '#4A5560', 'soft' => '#E8EBEE'],
        'ocean' => ['label' => 'Ocean', 'hex' => '#2F6F7E', 'soft' => '#E3EEF1'],
        'plum' => ['label' => 'Plum', 'hex' => '#6B4A6E', 'soft' => '#EFE7F0'],
    ];

    public const DEFAULT = 'moss';

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::PALETTE);
    }

    public static function hex(?string $key): string
    {
        return self::PALETTE[$key]['hex'] ?? self::PALETTE[self::DEFAULT]['hex'];
    }

    /** The accent as a text colour: at least 4.5:1 on its own tint and on the canvas. */
    public static function text(?string $key): string
    {
        return self::PALETTE[$key]['text'] ?? self::hex($key);
    }

    public static function soft(?string $key): string
    {
        return self::PALETTE[$key]['soft'] ?? self::PALETTE[self::DEFAULT]['soft'];
    }
}
