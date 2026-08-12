<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            /*
             * role becomes a plain string rather than gaining a third enum value.
             *
             * Altering an enum is the one schema change that differs meaningfully between MySQL
             * and SQLite — MySQL rewrites the column definition, SQLite rebuilds the table around
             * a CHECK constraint. A short varchar behaves identically on both and costs nothing;
             * the set of valid roles is enforced in the model, where it can also be read.
             */
            $t->string('role', 20)->default('joiner')->change();

            // Suspension, not deletion: a host with bookings in flight cannot simply vanish, and
            // the joiners on those trips still need the record.
            $t->dateTime('suspended_at')->nullable()->after('handle');
            $t->string('suspended_reason')->nullable()->after('suspended_at');
        });

        Schema::table('reviews', function (Blueprint $t) {
            // Hidden rather than removed, so a moderation decision can be undone and the original
            // words survive an argument about what was said.
            $t->dateTime('hidden_at')->nullable();
            $t->string('hidden_reason')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['suspended_at', 'suspended_reason']);
        });

        Schema::table('reviews', function (Blueprint $t) {
            $t->dropColumn(['hidden_at', 'hidden_reason']);
        });
    }
};
