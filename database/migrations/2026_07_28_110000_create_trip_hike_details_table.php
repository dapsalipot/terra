<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A separate one-to-one table rather than columns on `trips`: only hikes carry these,
        // and the trips table should not grow ten nullable columns that most rows never use.
        Schema::create('trip_hike_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('trail_name')->nullable();          // "Akiki–Ambangeg traverse"
            $table->string('jump_off')->nullable();            // trailhead barangay
            $table->unsignedSmallInteger('masl')->nullable();  // summit metres above sea level
            $table->unsignedSmallInteger('elevation_gain_m')->nullable();
            $table->decimal('distance_km', 5, 1)->nullable();
            $table->decimal('duration_hours', 4, 1)->nullable();

            $table->unsignedTinyInteger('difficulty')->nullable();   // PinoyMountaineer 1–9
            $table->unsignedTinyInteger('trail_class')->nullable();  // Yosemite 1–5
            $table->string('trail_type', 20)->nullable();            // out_and_back | loop | traverse | point_to_point

            $table->json('features')->nullable();       // allow-listed TrailFeatures keys
            $table->json('pulag_factors')->nullable();  // conditions that push difficulty up
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_hike_details');
    }
};
