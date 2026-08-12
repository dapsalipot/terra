<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trip_departures', function (Blueprint $t) {
            $t->id();
            $t->foreignId('trip_id')->constrained()->cascadeOnDelete();
            $t->dateTime('starts_at');
            $t->dateTime('ends_at')->nullable();
            $t->unsignedSmallInteger('capacity')->default(12);
            $t->enum('status', ['open', 'full', 'completed', 'cancelled'])->default('open');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_departures');
    }
};
