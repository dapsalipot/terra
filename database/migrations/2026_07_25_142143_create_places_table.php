<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('places', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('slug')->unique();
            $t->string('country');
            $t->string('region')->nullable();
            $t->unsignedInteger('elevation_m')->nullable();
            $t->enum('difficulty', ['easy', 'challenging', 'hard'])->default('challenging');
            $t->decimal('aggregate_rating', 2, 1)->nullable();
            $t->unsignedInteger('reviews_count')->default(0);
            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('places'); }
};
