<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservations', function (Blueprint $t) {
            $t->id();
            $t->foreignId('trip_departure_id')->constrained()->cascadeOnDelete();
            $t->foreignId('joiner_id')->nullable()->constrained('users')->nullOnDelete();
            $t->string('guest_name')->nullable();
            $t->string('guest_phone')->nullable();
            $t->enum('source', ['app', 'manual'])->default('app');
            $t->unsignedTinyInteger('party_size')->default(1);
            $t->enum('status', [
                'pending', 'awaiting_payment', 'payment_submitted', 'confirmed', 'cancelled',
            ])->default('pending');
            $t->dateTime('expires_at')->nullable();
            $t->dateTime('confirmed_at')->nullable();
            $t->dateTime('cancelled_at')->nullable();
            $t->string('cancel_reason')->nullable();
            $t->timestamps();

            $t->index(['trip_departure_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservations');
    }
};
