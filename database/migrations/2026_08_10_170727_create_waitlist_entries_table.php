<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waitlist_entries', function (Blueprint $t) {
            $t->id();
            $t->foreignId('trip_departure_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->nullable()->constrained()->nullOnDelete();

            // Contact rather than an account, because the whole booking flow works for guests and
            // a waitlist that demanded a login would turn away the people it exists to catch.
            $t->string('name');
            $t->string('email');
            $t->string('phone')->nullable();
            $t->unsignedSmallInteger('party_size')->default(1);

            // Stamped when they are told a seat opened. Nobody is told twice for the same list:
            // seats free up constantly through expired holds, and a mailing on every one of them
            // is how a helpful alert becomes something people filter to spam.
            $t->dateTime('notified_at')->nullable();
            $t->timestamps();

            // Ordering is first-come, and the query asks "who is still waiting" every time.
            $t->index(['trip_departure_id', 'notified_at']);
            $t->unique(['trip_departure_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waitlist_entries');
    }
};
