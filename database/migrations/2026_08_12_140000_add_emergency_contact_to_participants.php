<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Who to call if something happens on the mountain.
         *
         * Nullable at the database level even though the form requires it, and deliberately so:
         * every booking already taken has none, and the only ways to make the column NOT NULL
         * are to invent contacts or to delete history. Neither is acceptable for a field whose
         * entire purpose is being correct in an emergency.
         *
         * Required going forward is enforced where it can actually be satisfied — the
         * registration form and the edit modal. A legacy participant without one is reported as
         * incomplete, which is true, and surfaces in the host's "details missing" filter and the
         * joiner's own to-do rather than being silently absent.
         */
        Schema::table('reservation_participants', function (Blueprint $table) {
            $table->string('emergency_contact_name', 120)->nullable()->after('phone');
            $table->string('emergency_contact_phone', 32)->nullable()->after('emergency_contact_name');
        });
    }

    public function down(): void
    {
        Schema::table('reservation_participants', function (Blueprint $table) {
            $table->dropColumn(['emergency_contact_name', 'emergency_contact_phone']);
        });
    }
};
