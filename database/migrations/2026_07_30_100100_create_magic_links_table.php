<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('magic_links', function (Blueprint $t) {
            $t->id();
            // sha256 of the raw token. The raw value lives only in the URL and the email body.
            $t->string('token', 64)->unique();
            $t->string('purpose', 24);          // 'login' | 'attach'  ('invite' arrives in Spec 2)
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('email')->nullable();
            $t->foreignId('reservation_id')->nullable()->constrained()->nullOnDelete();
            $t->dateTime('expires_at');
            $t->dateTime('used_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void { Schema::dropIfExists('magic_links'); }
};
