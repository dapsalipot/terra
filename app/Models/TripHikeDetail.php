<?php

namespace App\Models;

use App\Support\HikeGrades;
use Illuminate\Database\Eloquent\Model;

class TripHikeDetail extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'pulag_factors' => 'array',
            'distance_km' => 'float',
            'duration_hours' => 'float',
        ];
    }

    public function destination()
    {
        return $this->belongsTo(TripDestination::class, 'trip_destination_id');
    }

    /** "Major climb" / "Minor climb" / "Hardcore" — how PH hikers actually talk about a mountain. */
    public function getBandAttribute(): ?string
    {
        return HikeGrades::band($this->difficulty);
    }

    public function getDifficultyLabelAttribute(): ?string
    {
        return HikeGrades::difficultyLabel($this->difficulty);
    }

    public function getTrailClassLabelAttribute(): ?string
    {
        return HikeGrades::trailClassLabel($this->trail_class);
    }

    public function getTrailTypeLabelAttribute(): ?string
    {
        return HikeGrades::trailTypeLabel($this->trail_type);
    }

    /** True when there is enough here to be worth rendering a trail panel. */
    public function hasContent(): bool
    {
        return filled($this->masl) || filled($this->distance_km) || filled($this->difficulty)
            || filled($this->trail_class) || filled($this->trail_type) || filled($this->features)
            || filled($this->elevation_gain_m) || filled($this->duration_hours) || filled($this->trail_name);
    }
}
