<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Being confirmed and being paid in full are two different facts.
 *
 * They used to be one: money() read `status === 'confirmed'` and credited the deposit, so a host
 * had no way to say "they have now settled the balance" and no way to take it back if they had
 * marked it in error. On a deposit trip that left the collected total permanently understated,
 * and on any trip it meant confirming a seat silently asserted money had changed hands.
 *
 * Nullable rather than a boolean, because *when* is the question a host actually asks — and
 * `fully_paid_by` records who decided it, the same audit trail payment_submissions already keeps
 * for confirmations.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            $table->dateTime('fully_paid_at')->nullable()->after('confirmed_at');
            $table->foreignId('fully_paid_by')->nullable()->after('fully_paid_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table) {
            // Drop the FK before its column, or MySQL keeps the index and the drop fails.
            $table->dropConstrainedForeignId('fully_paid_by');
            $table->dropColumn('fully_paid_at');
        });
    }
};
