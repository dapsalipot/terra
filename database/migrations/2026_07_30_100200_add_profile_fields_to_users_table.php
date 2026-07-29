<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('first_name')->nullable()->after('name');
            $t->string('middle_name')->nullable()->after('first_name');
            $t->string('last_name')->nullable()->after('middle_name');
            $t->date('birthday')->nullable()->after('phone');
            $t->string('gender', 24)->nullable()->after('birthday');

            // A passwordless account is encoded honestly rather than with a random-hash decoy,
            // so `password IS NULL` can drive the "email me a sign-in link" affordance.
            // Safe: AbstractHasher::check() returns false when the stored hash is null.
            $t->string('password')->nullable()->change();
        });
    }

    public function down(): void
    {
        // A passwordless account cannot survive this column becoming NOT NULL. Give those rows
        // an unusable hash first: the account stays intact and is recoverable through a password
        // reset, whereas letting the ALTER fail would drop the columns and leave the schema
        // disagreeing with the migrations ledger (MySQL DDL does not roll back).
        DB::table('users')->whereNull('password')->update([
            'password' => Hash::make(Str::random(64)),
        ]);

        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn(['first_name', 'middle_name', 'last_name', 'birthday', 'gender']);
            $t->string('password')->nullable(false)->change();
        });
    }
};
