<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The departure is the room (one crew per departure). No separate rooms table for v1.
        Schema::create('messages', function (Blueprint $t) {
            $t->id();
            $t->foreignId('trip_departure_id')->constrained()->cascadeOnDelete();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('body');
            $t->boolean('pinned')->default(false);
            $t->timestamps();
            $t->index(['trip_departure_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
