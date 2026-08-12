<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trips', function (Blueprint $t) {
            // Off by default: a permit-controlled climb needs an ID from every head, a beach day
            // does not, and asking for one you do not need is asking a stranger for their
            // passport for no reason.
            $t->boolean('requires_id')->default(false)->after('packing_items');
        });

        Schema::table('reservation_participants', function (Blueprint $t) {
            // Private disk, like proof of payment — never public storage. The path is the whole
            // record; there is no thumbnail and no second copy.
            $t->string('id_image')->nullable()->after('phone');
            $t->dateTime('id_uploaded_at')->nullable()->after('id_image');
        });
    }

    public function down(): void
    {
        Schema::table('trips', fn (Blueprint $t) => $t->dropColumn('requires_id'));
        Schema::table('reservation_participants', fn (Blueprint $t) => $t->dropColumn(['id_image', 'id_uploaded_at']));
    }
};
