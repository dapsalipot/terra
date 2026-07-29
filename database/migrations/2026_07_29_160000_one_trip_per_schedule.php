<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * One trip per schedule.
     *
     * A trip used to own many departures, which meant every date shared one set of
     * inclusions, pickups and trail detail. Hosts run the same mountain in March and in
     * December with different vans, different guides and different prices, and had no way
     * to say so.
     *
     * `trip_departures` deliberately stays: reservations, chat, the roster, the review
     * gate and the QR flow are all keyed to a departure, and rehoming twelve files to
     * chase a tidier schema would be a lot of risk for no behaviour. Instead each trip now
     * holds exactly one departure, and trips that were published together share a
     * `trip_group_id` so the console can still present them as one offering.
     */
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->uuid('trip_group_id')->nullable()->index()->after('host_id');
        });

        // Split existing multi-departure trips so the new rule holds for old data too.
        foreach (DB::table('trips')->orderBy('id')->get() as $trip) {
            $departures = DB::table('trip_departures')->where('trip_id', $trip->id)->orderBy('starts_at')->get();
            $group = (string) Str::uuid();

            DB::table('trips')->where('id', $trip->id)->update(['trip_group_id' => $group]);

            // the first departure stays put; every extra one becomes its own trip
            foreach ($departures->skip(1) as $i => $departure) {
                $clone = collect((array) $trip)->except('id')->all();
                $clone['trip_group_id'] = $group;
                $clone['slug'] = $trip->slug.'-'.($i + 2);
                $clone['created_at'] = now();
                $clone['updated_at'] = now();

                $newTripId = DB::table('trips')->insertGetId($clone);

                DB::table('trip_departures')->where('id', $departure->id)->update(['trip_id' => $newTripId]);

                foreach (['trip_destinations', 'trip_photos', 'trip_meetups', 'trip_inclusions'] as $table) {
                    foreach (DB::table($table)->where('trip_id', $trip->id)->get() as $row) {
                        $copy = collect((array) $row)->except('id')->all();
                        $copy['trip_id'] = $newTripId;
                        DB::table($table)->insert($copy);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('trip_group_id');
        });
    }
};
