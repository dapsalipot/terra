<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reservation_participants', function (Blueprint $t) {
            $t->id();
            $t->foreignId('reservation_id')->constrained()->cascadeOnDelete();
            $t->unsignedTinyInteger('position')->default(0);
            $t->boolean('is_booker')->default(false);
            $t->string('first_name')->nullable();
            $t->string('middle_name')->nullable();
            $t->string('last_name')->nullable();
            $t->date('birthday')->nullable();
            $t->string('gender', 24)->nullable();
            $t->string('email')->nullable();
            $t->string('phone', 32)->nullable();
            $t->timestamps();

            $t->index(['reservation_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reservation_participants');
    }
};
