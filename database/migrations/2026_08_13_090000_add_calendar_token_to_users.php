<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The secret in a calendar subscription URL.
         *
         * A feed is fetched by a calendar server with no session and no way to log in, so the URL
         * itself is the credential. Separate from anything else the user owns: rotating it revokes
         * every subscription without touching their password or their bookings, and it leaking
         * exposes a list of trips rather than an account.
         *
         * Nullable and minted on first use — most joiners never subscribe, and a column full of
         * unused secrets is a bigger surface than an empty one.
         */
        Schema::table('users', function (Blueprint $table) {
            $table->string('calendar_token', 64)->nullable()->unique()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('calendar_token');
        });
    }
};
