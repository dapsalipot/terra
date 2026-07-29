<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Two things the wizard could not express:
     *
     * 1. How long a trip spends at each stop. A major climb is two or three days on the
     *    mountain, not "Day 1" — and a joiner needs to know how many nights they are
     *    packing for before they commit.
     * 2. Where and when the trip drops people off. Meetup points only ever described the
     *    pickup, so the return leg was invisible. `kind` splits the two and `time` gives
     *    both an actual clock time instead of it being buried in the label text.
     */
    public function up(): void
    {
        Schema::table('trip_destinations', function (Blueprint $table) {
            $table->unsignedTinyInteger('days')->nullable()->after('position');
            $table->unsignedTinyInteger('nights')->nullable()->after('days');
        });

        Schema::table('trip_meetups', function (Blueprint $table) {
            $table->string('kind', 10)->default('pickup')->after('trip_id');
            $table->time('time')->nullable()->after('kind');
        });
    }

    public function down(): void
    {
        Schema::table('trip_destinations', function (Blueprint $table) {
            $table->dropColumn(['days', 'nights']);
        });

        Schema::table('trip_meetups', function (Blueprint $table) {
            $table->dropColumn(['kind', 'time']);
        });
    }
};
