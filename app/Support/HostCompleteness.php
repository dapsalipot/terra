<?php

namespace App\Support;

use App\Models\User;

/**
 * How much of their page a host has filled in, and what is still missing.
 *
 * A host's page is the thing joiners choose between, so an empty one costs them bookings. The score
 * exists to say so plainly and to name the next thing to add, weighted towards the fields that carry
 * personality (a tagline, vibes, reasons to join) rather than the ones that are just admin.
 */
class HostCompleteness
{
    /** key => [weight, label, hint]. The weights add to 100. */
    public const ITEMS = [
        'avatar' => [10, 'Profile photo', 'A face or a logo people can recognise.'],
        'cover' => [10, 'Cover photo', 'Your best trail photo, across the top of your page.'],
        'tagline' => [15, 'One-line promise', 'What a joiner gets with you that they would not get elsewhere.'],
        'bio' => [15, 'About you', 'Two or three sentences on who you are and how you run trips.'],
        'location' => [5, 'Where you are based', 'Joiners like to know who is local.'],
        'vibes' => [15, 'How your trips feel', 'Pick at least two, so joiners can find your kind of trip.'],
        'highlights' => [15, 'Reasons to join you', 'Add at least two short lines.'],
        'links' => [5, 'A link to your other pages', 'Instagram, Facebook or a website, so people can see you are real.'],
        'wall' => [10, 'A post on your wall', 'Photos from a recent trip show what it is actually like.'],
    ];

    /**
     * @param  array{avatar: bool, cover: bool, tagline: string, bio: string, location: string, vibes: int, highlights: int, links: int, posts: int}  $facts
     * @return array{percent: int, done: array<string, string>, todo: array<string, array{label: string, hint: string}>}
     */
    public static function score(array $facts): array
    {
        $passed = [
            'avatar' => (bool) $facts['avatar'],
            'cover' => (bool) $facts['cover'],
            'tagline' => filled(trim($facts['tagline'])),
            'bio' => mb_strlen(trim($facts['bio'])) >= 40,
            'location' => filled(trim($facts['location'])),
            'vibes' => $facts['vibes'] >= 2,
            'highlights' => $facts['highlights'] >= 2,
            'links' => $facts['links'] >= 1,
            'wall' => $facts['posts'] >= 1,
        ];

        $percent = 0;
        $done = [];
        $todo = [];

        foreach (self::ITEMS as $key => [$weight, $label, $hint]) {
            if ($passed[$key]) {
                $percent += $weight;
                $done[$key] = $label;
            } else {
                $todo[$key] = ['label' => $label, 'hint' => $hint];
            }
        }

        return ['percent' => $percent, 'done' => $done, 'todo' => $todo];
    }

    /** The facts as saved, for anywhere that is not mid-edit. */
    public static function for(User $host): array
    {
        $p = $host->hostProfile;

        return self::score([
            'avatar' => filled($host->avatar),
            'cover' => filled($p?->cover_photo),
            'tagline' => (string) $p?->tagline,
            'bio' => (string) $p?->bio,
            'location' => (string) $p?->location,
            'vibes' => count($p?->vibes ?? []),
            'highlights' => count($p?->highlightLines() ?? []),
            'links' => count(array_filter($p?->links ?? [])),
            'posts' => $host->posts()->count(),
        ]);
    }
}
