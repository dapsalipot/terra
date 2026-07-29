<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('host_profiles', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->text('bio')->nullable();
            $t->string('cover_photo')->nullable();
            $t->string('currency', 3)->default('PHP');
            $t->json('payment_methods')->nullable();
            $t->unsignedInteger('trips_count')->default(0);
            $t->unsignedInteger('guests_count')->default(0);
            $t->unsignedInteger('followers_count')->default(0);
            $t->decimal('rating', 2, 1)->nullable();
            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('host_profiles'); }
};
