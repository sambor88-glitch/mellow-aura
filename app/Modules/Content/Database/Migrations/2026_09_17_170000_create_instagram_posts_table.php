<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The latest posts from Kasia's Instagram, with each photo saved on the shop's own disk.
     */
    public function up(): void
    {
        Schema::create('instagram_posts', function (Blueprint $table) {
            $table->id();
            $table->string('external_id')->unique();
            $table->string('permalink');
            $table->text('caption')->nullable();
            $table->string('alt_text')->nullable();
            $table->string('image_path');
            $table->dateTime('posted_at');
            $table->timestamps();

            $table->index('posted_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('instagram_posts');
    }
};
