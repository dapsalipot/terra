<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservation_participants', function (Blueprint $t) {
            /*
             * The pass code, one per traveller.
             *
             * Per person rather than per booking because attendance is per person: a party of
             * four arriving in two cars is two check-ins, and a single booking-level code cannot
             * express that. Unique and indexed because the scanner looks a code up on every scan.
             */
            $t->string('pass_code', 24)->nullable()->unique()->after('id_uploaded_at');
            $t->dateTime('checked_in_at')->nullable()->after('pass_code');
            $t->foreignId('checked_in_by')->nullable()->after('checked_in_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservation_participants', function (Blueprint $t) {
            $t->dropConstrainedForeignId('checked_in_by');
            $t->dropColumn(['pass_code', 'checked_in_at']);
        });
    }
};
