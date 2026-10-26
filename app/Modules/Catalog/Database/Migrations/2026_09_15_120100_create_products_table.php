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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->text('description')->nullable();
            $table->string('seo_description')->nullable();
            $table->json('dimensions')->nullable();
            $table->text('care_note')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('is_one_off')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('stamp_enabled')->default(false);
            $table->json('occasions')->nullable();
            $table->json('recipients')->nullable();
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
