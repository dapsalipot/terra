<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('host_profiles', function (Blueprint $table) {
            // What a joiner is choosing between when the trip is the same: who leads it and how it feels.
            $table->json('vibes')->nullable()->after('accent');          // keys from App\Support\Vibes
            $table->json('highlights')->nullable()->after('vibes');      // up to three "why join us" lines
            $table->string('backdrop', 20)->default('trail')->after('highlights');   // key from App\Support\Backdrops
            $table->timestamp('verified_at')->nullable()->after('backdrop');          // set by an admin, never by the host
        });
    }

    public function down(): void
    {
        Schema::table('host_profiles', function (Blueprint $table) {
            $table->dropColumn(['vibes', 'highlights', 'backdrop', 'verified_at']);
        });
    }
};
