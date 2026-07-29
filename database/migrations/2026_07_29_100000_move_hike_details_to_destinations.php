<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trail detail belongs to a stop, not to a trip. A traverse that takes in Pulag and
     * Ulap has two summits, two distances and two sets of trail features — hanging one
     * row off the trip forced them to share, which is wrong for any multi-mountain trip.
     */
    public function up(): void
    {
        Schema::dropIfExists('trip_hike_details');

        Schema::create('trip_hike_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_destination_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('trail_name')->nullable();
            $table->string('jump_off')->nullable();
            $table->unsignedSmallInteger('masl')->nullable();
            $table->unsignedSmallInteger('elevation_gain_m')->nullable();
            $table->decimal('distance_km', 5, 1)->nullable();
            $table->decimal('duration_hours', 4, 1)->nullable();
            $table->unsignedTinyInteger('difficulty')->nullable();   // PinoyMountaineer 1–9
            $table->unsignedTinyInteger('trail_class')->nullable();  // Yosemite 1–5
            $table->string('trail_type', 20)->nullable();
            $table->json('features')->nullable();
            $table->json('pulag_factors')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_hike_details');
    }
};
