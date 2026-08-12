<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            /*
             * A snapshot, not a derived figure — and it has to be, because money() cannot answer
             * this after the fact. isFullyPaid() excludes cancelled bookings and the paid branch
             * keys off a live status, so the moment a booking is cancelled the amount it had paid
             * becomes unrecoverable. Captured at the point of cancellation or lost.
             */
            $t->decimal('refund_due', 10, 2)->nullable()->after('cancel_reason');
            $t->dateTime('refunded_at')->nullable()->after('refund_due');
            $t->foreignId('refunded_by')->nullable()->after('refunded_at')->constrained('users')->nullOnDelete();
            $t->string('refund_note')->nullable()->after('refunded_by');
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            $t->dropConstrainedForeignId('refunded_by');
            $t->dropColumn(['refund_due', 'refunded_at', 'refund_note']);
        });
    }
};
