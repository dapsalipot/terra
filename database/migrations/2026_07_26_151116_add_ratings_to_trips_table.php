<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $t) {
            $t->decimal('aggregate_rating', 2, 1)->nullable()->after('status');
            $t->unsignedInteger('reviews_count')->default(0)->after('aggregate_rating');
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $t) {
            $t->dropColumn(['aggregate_rating', 'reviews_count']);
        });
    }
};
