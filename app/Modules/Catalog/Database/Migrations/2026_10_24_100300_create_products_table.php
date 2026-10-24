<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->text('description')->nullable();
            // Optional, in the units shown in the panel: cm, mm for thickness, ml for capacity.
            $table->json('dimensions')->nullable();
            // Replaces the general care rule when set.
            $table->string('care_note')->nullable();
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

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
