<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * The trip carries the policy; the booking carries the resolved date.
         *
         * Two payment types, two deadlines: the downpayment (only meaningful on a trip that takes
         * one) and the full settlement. Each can be expressed either as a stretch of time after
         * the host confirms the seat, or as a fixed calendar date the whole group works to. Hours
         * rather than a number-plus-unit pair: the form offers days and multiplies, and rendering
         * divides back when it comes out even, which is one column instead of two for a
         * distinction nobody queries on.
         */
        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedInteger('downpayment_due_hours')->nullable()->after('deposit_amount');
            $table->dateTime('downpayment_due_at')->nullable()->after('downpayment_due_hours');
            $table->unsignedInteger('full_payment_due_hours')->nullable()->after('downpayment_due_at');
            $table->dateTime('full_payment_due_at')->nullable()->after('full_payment_due_hours');
        });

        /*
         * Resolved onto the booking when it is confirmed, and never recomputed.
         *
         * A host who edits the trip's terms in September must not retroactively move a deadline
         * somebody was told about in August — the date a joiner was given is a promise, not a
         * derived value. Storing it also makes "who is overdue" a plain comparison the roster can
         * filter on, instead of something only PHP can work out per row.
         */
        Schema::table('reservations', function (Blueprint $table) {
            $table->dateTime('downpayment_due_at')->nullable()->after('confirmed_at');
            $table->dateTime('full_payment_due_at')->nullable()->after('downpayment_due_at');
            $table->index(['status', 'full_payment_due_at']);
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropColumn([
                'downpayment_due_hours', 'downpayment_due_at',
                'full_payment_due_hours', 'full_payment_due_at',
            ]);
        });

        Schema::table('reservations', function (Blueprint $table) {
            $table->dropIndex(['status', 'full_payment_due_at']);
            $table->dropColumn(['downpayment_due_at', 'full_payment_due_at']);
        });
    }
};
