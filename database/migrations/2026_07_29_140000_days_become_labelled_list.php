<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A stop's length is now a list of days, each with its own description, rather than
     * a count plus a single shared label. "3 days" told a joiner nothing; "Day 1 — trek
     * to Camp 2, Day 2 — summit push, Day 3 — descent" is the actual itinerary.
     *
     * Nights are gone: they were a second number describing the same span, and a host
     * filling both in got to disagree with themselves.
     */
    public function up(): void
    {
        Schema::table('trip_destinations', function (Blueprint $table) {
            $table->dropColumn(['days', 'nights', 'day_label']);
        });

        Schema::table('trip_destinations', function (Blueprint $table) {
            $table->json('days')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('trip_destinations', function (Blueprint $table) {
            $table->dropColumn('days');
        });

        Schema::table('trip_destinations', function (Blueprint $table) {
            $table->unsignedTinyInteger('days')->nullable()->after('position');
            $table->unsignedTinyInteger('nights')->nullable()->after('days');
            $table->string('day_label')->nullable()->after('nights');
        });
    }
};
