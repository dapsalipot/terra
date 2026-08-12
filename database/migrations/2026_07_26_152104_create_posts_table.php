<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $t->foreignId('trip_id')->nullable()->constrained()->nullOnDelete();
            $t->enum('type', ['recap', 'update'])->default('update');
            $t->text('body');
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
