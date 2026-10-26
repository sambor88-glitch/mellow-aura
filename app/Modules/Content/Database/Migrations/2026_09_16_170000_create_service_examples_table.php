<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A „przed i po” pair on a service page. The two photos are media in the before and after collections.
        Schema::create('service_examples', function (Blueprint $table) {
            $table->id();
            // scarf or imprint
            $table->string('service', 20)->index();
            $table->string('caption', 160)->nullable();
            // For a screen reader; empty falls back to a general description of the photo.
            $table->string('before_alt', 160)->nullable();
            $table->string('after_alt', 160)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('service_examples');
    }
};
