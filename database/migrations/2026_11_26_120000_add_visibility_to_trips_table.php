<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Who can find a trip in a listing, kept apart from `status`: a link-only trip is still published,
         * and its page, join page and booking page open by link whatever this says.
         */
        Schema::table('trips', function (Blueprint $table) {
            $table->string('visibility', 12)->default('public')->after('status');   // key from App\Support\Visibility
            $table->timestamp('public_at')->nullable()->after('visibility');         // when a followers-first trip opens to everyone
            $table->index(['status', 'visibility', 'public_at'], 'trips_listing_index');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropIndex('trips_listing_index');
            $table->dropColumn(['visibility', 'public_at']);
        });
    }
};
