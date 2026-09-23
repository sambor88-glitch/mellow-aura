<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The catalog in languages other than Polish. Polish stays in the tables themselves; a row here holds one other
 * language. No row means the category, product or variant does not exist in that language — it is left out of
 * /en/ rather than shown in Polish.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('slug');
            $table->string('name');
            $table->string('seo_title')->nullable();
            $table->string('seo_description')->nullable();
            $table->timestamps();
            $table->unique(['category_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('product_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('slug');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('seo_description')->nullable();
            $table->text('care_note')->nullable();
            $table->string('deviation')->nullable();
            $table->string('size_tolerance')->nullable();
            $table->text('safety_warnings')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'locale']);
            $table->unique(['locale', 'slug']);
        });

        Schema::create('product_variant_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('label');
            $table->timestamps();
            $table->unique(['product_variant_id', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_translations');
        Schema::dropIfExists('product_translations');
        Schema::dropIfExists('category_translations');
    }
};
