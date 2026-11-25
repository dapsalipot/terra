<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $t) {
            // Tarana is mainly hiking, but joiners is group trips of every kind.
            $t->string('category')->default('hike')->after('region');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $t) {
            $t->dropColumn('category');
        });
    }
};
