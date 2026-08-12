<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            // Stamped when the "your hold is about to lapse" email goes out, so an hourly command
            // warns each booking once rather than every hour until it expires.
            $t->dateTime('expiry_reminded_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            $t->dropColumn('expiry_reminded_at');
        });
    }
};
