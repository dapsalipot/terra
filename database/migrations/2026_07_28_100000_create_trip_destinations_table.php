<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_destinations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->foreignId('place_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('day_label')->nullable();
            $table->timestamps();

            $table->index(['trip_id', 'position']);
        });

        // Backfill: every existing trip keeps its destination as stop 0.
        foreach (DB::table('trips')->whereNotNull('place_id')->get(['id', 'place_id']) as $trip) {
            DB::table('trip_destinations')->insert([
                'trip_id' => $trip->id,
                'place_id' => $trip->place_id,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_destinations');
    }
};
