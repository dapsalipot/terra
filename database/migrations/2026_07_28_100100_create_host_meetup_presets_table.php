<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('host_meetup_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->constrained('users')->cascadeOnDelete();
            $table->string('label');
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->string('image')->nullable();
            $table->string('note')->nullable();
            $table->timestamps();

            $table->index('host_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('host_meetup_presets');
    }
};
