<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            $t->uuid('public_token')->nullable()->unique()->after('id');
            $t->string('contact_first_name')->nullable()->after('guest_phone');
            $t->string('contact_middle_name')->nullable()->after('contact_first_name');
            $t->string('contact_last_name')->nullable()->after('contact_middle_name');
            $t->string('contact_email')->nullable()->after('contact_last_name');
            $t->string('contact_phone', 32)->nullable()->after('contact_email');
        });

        // Existing rows predate the token. Backfill so every booking is reachable by URL.
        DB::table('reservations')->whereNull('public_token')->orderBy('id')
            ->each(fn ($row) => DB::table('reservations')
                ->where('id', $row->id)
                ->update(['public_token' => (string) Str::uuid()]));
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $t) {
            $t->dropColumn([
                'public_token', 'contact_first_name', 'contact_middle_name',
                'contact_last_name', 'contact_email', 'contact_phone',
            ]);
        });
    }
};
