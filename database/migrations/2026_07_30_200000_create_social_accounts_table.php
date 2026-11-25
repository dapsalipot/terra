<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('social_accounts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->string('provider', 32);            // google | facebook | …
            $t->string('provider_id');             // the provider's own stable subject id
            $t->string('email')->nullable();       // as the provider reported it, for auditing
            $t->string('avatar')->nullable();
            $t->timestamps();

            /*
             * The provider's id is the identity, not the email — an email can change hands or be
             * re-pointed, while the subject id is stable for the life of the account. Unique on
             * the pair so one Google account cannot be attached to two Tarana users.
             */
            $t->unique(['provider', 'provider_id']);

            // A user gets at most one link per provider.
            $t->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
    }
};
