<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $t) {
            // A right of reply. Reviews were one-way, so a host with a fair answer to an unfair
            // review had nowhere to put it and the reader only ever saw one side.
            $t->text('reply_body')->nullable();
            $t->dateTime('replied_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reviews', fn (Blueprint $t) => $t->dropColumn(['reply_body', 'replied_at']));
    }
};
