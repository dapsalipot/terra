<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('host_profiles', function (Blueprint $table) {
            // Personalisation the host controls from their profile settings.
            $table->string('tagline')->nullable()->after('bio');
            $table->string('location')->nullable()->after('tagline');
            // Accent is a *key* into an allow-listed palette, never a free-form hex, so a host
            // can never push their page outside the Trail Earth design system.
            $table->string('accent', 20)->default('moss')->after('location');
            $table->json('links')->nullable()->after('accent');
        });
    }

    public function down(): void
    {
        Schema::table('host_profiles', function (Blueprint $table) {
            $table->dropColumn(['tagline', 'location', 'accent', 'links']);
        });
    }
};
