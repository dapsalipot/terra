<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_inclusions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('label');                // "Transport"
            $table->string('note')->nullable();     // "Air-conditioned van, Manila round trip"
            $table->string('image')->nullable();    // optional photo of what they actually get
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['trip_id', 'position']);
        });

        // Promote the existing JSON string list into rows so there is one read path.
        foreach (DB::table('trips')->whereNotNull('inclusions')->get(['id', 'inclusions']) as $trip) {
            $labels = json_decode($trip->inclusions, true);

            foreach (is_array($labels) ? array_values($labels) : [] as $i => $label) {
                if (! is_string($label) || $label === '') {
                    continue;
                }

                DB::table('trip_inclusions')->insert([
                    'trip_id' => $trip->id,
                    'label' => $label,
                    'position' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_inclusions');
    }
};
