<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
         * Money stops being inferred and starts being recorded.
         *
         * Until now a submission carried a method and a reference but no figure, so what had been
         * paid was derived: a verified submission meant `payable_now × party_size`, full stop.
         * That works only while a booking is one indivisible lump. It cannot express the thing
         * hosts actually deal with — four friends on one booking, two of whom have sent their
         * share — and it cannot express a joiner paying an odd amount because that is what they
         * had.
         *
         * `amount` is what the host says arrived. Nullable because every submission already made
         * has no figure attached, and inventing one would be worse than reading them the old way.
         */
        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->decimal('amount', 10, 2)->nullable()->after('reservation_id');
        });

        /*
         * Which travellers a payment covers.
         *
         * A pivot rather than a column on the participant, because one payment can settle several
         * people and a person can be settled across two payments — a deposit and a balance. The
         * alternative, a `paid_at` on the participant, throws away which money did it and cannot
         * survive the host taking one payment back.
         */
        Schema::create('participant_payment_submission', function (Blueprint $table) {
            $table->id();
            // Constraints named by hand: Laravel's generated names run past MySQL's 64-character
            // identifier limit for a table this long, and the failure only shows up on MySQL.
            $table->foreignId('payment_submission_id')
                ->constrained()->cascadeOnDelete()->name('pps_payment_fk');
            $table->foreignId('reservation_participant_id')
                ->constrained('reservation_participants')->cascadeOnDelete()->name('pps_participant_fk');
            $table->timestamps();

            $table->unique(['payment_submission_id', 'reservation_participant_id'], 'payment_participant_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participant_payment_submission');

        Schema::table('payment_submissions', function (Blueprint $table) {
            $table->dropColumn('amount');
        });
    }
};
