<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_meetups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('label');                 // "Cubao terminal"
            $table->string('address')->nullable();   // free-text address shown under the label
            // Coordinates drive the embedded map. Nullable so a host can add a spot before
            // they have the pin; 7dp is ~1cm precision, far beyond what a meetup needs.
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('image')->nullable();     // photo of the actual spot
            $table->string('note')->nullable();      // "look for the blue van"
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['trip_id', 'position']);
        });

        // Carry the old single free-text meetup across so no trip loses its meetup info.
        foreach (DB::table('trips')->whereNotNull('meetup_point')->get(['id', 'meetup_point']) as $trip) {
            DB::table('trip_meetups')->insert([
                'trip_id' => $trip->id,
                'label' => $trip->meetup_point,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_meetups');
    }
};
