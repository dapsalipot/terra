<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tiered pricing per schedule: early bird until a date, regular after, and any other
     * window the host wants. `trips.price` stays as the fallback and as the price a tier
     * is compared against, so a trip with no tiers behaves exactly as it did.
     *
     * A tier is attached to a trip, and a trip is now one schedule — so a host can
     * discount a slow December date without touching the March one.
     */
    public function up(): void
    {
        Schema::create('trip_price_tiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $table->string('label');                       // "Early bird", "Regular"
            $table->decimal('amount', 10, 2);
            $table->date('starts_at')->nullable();         // null = open-ended at the front
            $table->date('ends_at')->nullable();           // null = runs until departure
            $table->unsignedTinyInteger('position')->default(0);
            $table->timestamps();

            $table->index(['trip_id', 'position']);
        });

        Schema::table('trips', function (Blueprint $table) {
            // null = pay in full. A figure here is what a joiner owes to hold a place.
            $table->decimal('deposit_amount', 10, 2)->nullable()->after('price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_price_tiers');

        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn('deposit_amount');
        });
    }
};
