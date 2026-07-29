<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $t) {
            $t->id();
            $t->morphs('reviewable');                       // trip | user(host) | place
            $t->foreignId('joiner_id')->constrained('users')->cascadeOnDelete();
            $t->unsignedTinyInteger('rating');              // 1..5
            $t->text('body')->nullable();
            $t->timestamps();
            $t->unique(['joiner_id', 'reviewable_type', 'reviewable_id']);
        });
    }

    public function down(): void { Schema::dropIfExists('reviews'); }
};
