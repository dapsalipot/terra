<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->enum('role', ['joiner', 'host'])->default('joiner')->after('email');
            $t->string('handle')->nullable()->unique()->after('role');
            $t->string('avatar')->nullable()->after('handle');
            $t->string('phone')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['role', 'handle', 'avatar', 'phone']);
        });
    }
};
