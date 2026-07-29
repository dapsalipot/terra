<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('post_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->unsignedSmallInteger('position')->default(0);
            $table->timestamps();

            $table->index(['post_id', 'position']);
        });

        // Fold the single-image posts added earlier today into the new gallery table so the
        // carousel is the only read path and posts.image can retire.
        foreach (DB::table('posts')->whereNotNull('image')->get(['id', 'image']) as $post) {
            DB::table('post_images')->insert([
                'post_id' => $post->id,
                'path' => $post->image,
                'position' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('post_images');
    }
};
