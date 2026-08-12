<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who decided, not just that a decision happened. Every moderation action was anonymous:
        // the record said a review was hidden but never by whom, which is the first question
        // asked when the decision is challenged.
        Schema::table('reviews', function (Blueprint $t) {
            $t->foreignId('hidden_by')->nullable()->after('hidden_reason')->constrained('users')->nullOnDelete();
        });

        Schema::table('users', function (Blueprint $t) {
            $t->foreignId('suspended_by')->nullable()->after('suspended_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', fn (Blueprint $t) => $t->dropConstrainedForeignId('hidden_by'));
        Schema::table('users', fn (Blueprint $t) => $t->dropConstrainedForeignId('suspended_by'));
    }
};
