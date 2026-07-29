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
    public const PALETTE = [
        'moss' => ['label' => 'Moss', 'hex' => '#5E6B44', 'soft' => '#EDF0E6'],
        'pine' => ['label' => 'Pine', 'hex' => '#2C3826', 'soft' => '#E6EAE3'],
        'terra' => ['label' => 'Terracotta', 'hex' => '#BC4B26', 'soft' => '#F8E7E0'],
        'amber' => ['label' => 'Amber', 'hex' => '#A8761F', 'soft' => '#F8EEDC'],
        'clay' => ['label' => 'Clay', 'hex' => '#8C5A3C', 'soft' => '#F3E8E0'],
        'slate' => ['label' => 'Slate', 'hex' => '#4A5560', 'soft' => '#E8EBEE'],
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

    public static function soft(?string $key): string
    {
        return self::PALETTE[$key]['soft'] ?? self::PALETTE[self::DEFAULT]['soft'];
    }
}
