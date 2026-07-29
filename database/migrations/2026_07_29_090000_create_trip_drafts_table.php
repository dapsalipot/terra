<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One in-progress trip per host. The wizard writes here on every change so a dropped
     * session, a closed tab or an expired login never costs the host their work.
     *
     * The payload is the component's own state as JSON rather than a half-built Trip row:
     * a draft has no valid title or price yet, so it would fail the trips table's own
     * expectations, and partial rows would leak into every query that reads trips.
     */
    public function up(): void
    {
        Schema::create('trip_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('host_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->json('payload');
            $table->unsignedTinyInteger('step')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trip_drafts');
    }
};
