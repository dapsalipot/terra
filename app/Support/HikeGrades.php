<?php

namespace App\Support;

/**
 * Hiking grading systems, encoded from the standards Philippine hikers actually use.
 *
 * DIFFICULTY 1–9 — the PinoyMountaineer scale, the de-facto standard for PH mountains.
 *   1–4 = "minor climb" (day hikes, non-technical)
 *   5–8 = "major climb" (multi-day, full camping kit, endurance)
 *   9    = "hardcore" (16–24h days, technical hazards, summit not assured)
 *   Source: pinoymountaineer.com/2008/11/pinoymountaineer-difficulty-scale-2009.html
 *
 * TRAIL CLASS 1–5 — Yosemite Decimal System. Classes 1–4 are hiking/scrambling;
 *   class 5 is technical roped climbing. A route takes the class of its hardest move.
 */
class HikeGrades
{
    /** PinoyMountaineer 1–9, with the published definition for each level. */
    public const DIFFICULTY = [
        1 => ['label' => 'Very easy', 'note' => 'Under 30 minutes on paved roads or clear paths.'],
        2 => ['label' => 'Easy', 'note' => 'Single trail under 2 hours, not exceeding class 2.'],
        3 => ['label' => 'Mild', 'note' => 'Under 2 hours with class 3 parts, or 2–5 hours at class 2.'],
        4 => ['label' => 'Average', 'note' => '2–5 hours with class 3+ parts, or 5–9 hours at class 2.'],
        5 => ['label' => 'Moderate', 'note' => '5–9 hours with class 3+ parts, or long treks at class 2.'],
        6 => ['label' => 'Challenging', 'note' => 'Under class 5, with place-specific hazards beyond trail class.'],
        7 => ['label' => 'Difficult', 'note' => '5–9 hours at class 4+ in cold weather, or 3+ days.'],
        8 => ['label' => 'Strenuous', 'note' => '3+ days across varied, potentially hazardous terrain.'],
        9 => ['label' => 'Technical', 'note' => 'Class 5–6 over 8 hours, or 4-day exploratory climbs.'],
    ];

    /** Yosemite Decimal System, the technical axis the difficulty scale references. */
    public const TRAIL_CLASS = [
        1 => ['label' => 'Class 1', 'note' => 'Walking on a trail.'],
        2 => ['label' => 'Class 2', 'note' => 'Simple scrambling; hands used occasionally.'],
        3 => ['label' => 'Class 3', 'note' => 'Scrambling. A rope may be carried.'],
        4 => ['label' => 'Class 4', 'note' => 'Simple climbing with exposure. A fall could be fatal.'],
        5 => ['label' => 'Class 5', 'note' => 'Technical climbing — ropes, belays and protection.'],
    ];

    public const TRAIL_TYPE = [
        'out_and_back' => 'Out and back',
        'loop' => 'Loop',
        'traverse' => 'Traverse',
        'point_to_point' => 'Point to point',
    ];

    /**
     * PULAG factors — conditions that add +1 to a mountain's difficulty rating.
     * (Named for Mount Pulag, where several of them routinely apply at once.)
     */
    public const PULAG_FACTORS = [
        'precipitation' => 'Rain over half the trek',
        'unestablished' => 'Unestablished or flooded trail',
        'low_temperature' => 'Low temperatures',
        'animals' => 'Wildlife interference',
        'gusts' => 'Strong wind (50kph+)',
    ];

    /**
     * Every lookup below tolerates an out-of-range value and returns null. Views render
     * rejected input too — Livewire re-renders with whatever the host last typed — so a
     * grade of 12 must fall through quietly rather than blow up the page.
     */
    public static function band(?int $difficulty): ?string
    {
        return match (true) {
            ! isset(self::DIFFICULTY[$difficulty]) => null,
            $difficulty <= 4 => 'Minor climb',
            $difficulty <= 8 => 'Major climb',
            default => 'Hardcore',
        };
    }

    public static function difficultyLabel(?int $d): ?string
    {
        return self::DIFFICULTY[$d]['label'] ?? null;
    }

    public static function difficultyNote(?int $d): ?string
    {
        return self::DIFFICULTY[$d]['note'] ?? null;
    }

    public static function trailClassLabel(?int $c): ?string
    {
        return self::TRAIL_CLASS[$c]['label'] ?? null;
    }

    public static function trailClassNote(?int $c): ?string
    {
        return self::TRAIL_CLASS[$c]['note'] ?? null;
    }

    public static function trailTypeLabel(?string $t): ?string
    {
        return self::TRAIL_TYPE[$t] ?? null;
    }
}
