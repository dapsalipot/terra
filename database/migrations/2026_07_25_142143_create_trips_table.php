<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trips', function (Blueprint $t) {
            $t->id();
            $t->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('place_id')->constrained();
            $t->string('title');
            $t->string('slug')->unique();
            $t->text('description')->nullable();
            $t->string('meetup_point')->nullable();
            $t->decimal('price', 10, 2);
            $t->json('inclusions')->nullable();
            $t->string('cover_photo')->nullable();
            $t->enum('status', ['draft', 'published', 'archived'])->default('draft');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trips');
    }
};
