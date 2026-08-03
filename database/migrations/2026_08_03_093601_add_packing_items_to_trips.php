<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What joiners should bring, as allow-listed keys from App\Support\PackingItems.
 *
 * A json column rather than a pivot table, matching `inclusions` alongside it: the set is small,
 * always read whole with the trip, and never queried across trips. Nullable so every existing
 * trip stays valid — a trip with no packing list is one whose host has not filled it in, which is
 * a different thing from a trip that needs nothing brought.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->json('packing_items')->nullable()->after('inclusions');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('packing_items');
        });
    }
};
