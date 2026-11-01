<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Categories in a tree (MA-351): Glina and Szycie at the top, up to three levels. A category with products or
 * subcategories cannot be deleted, so the parent key does not cascade.
 *
 * A product has one main category (its address, breadcrumbs, the Google feed) and may also show in others —
 * „Pokaż też w” in the panel, kept in category_product.
 *
 * An address a category had before keeps working: category_redirects sends it on with a 301.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL does not roll back ALTER TABLE, so a deploy that failed halfway may have left part of this behind.
        if (! Schema::hasColumn('categories', 'parent_id')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->foreignId('parent_id')->nullable()->after('id')->constrained('categories')->restrictOnDelete();
            });
        }

        if (! Schema::hasTable('category_product')) {
            Schema::create('category_product', function (Blueprint $table) {
                $table->foreignId('category_id')->constrained()->cascadeOnDelete();
                $table->foreignId('product_id')->constrained()->cascadeOnDelete();
                $table->primary(['category_id', 'product_id']);
            });
        }

        if (! Schema::hasTable('category_redirects')) {
            Schema::create('category_redirects', function (Blueprint $table) {
                $table->id();
                $table->foreignId('category_id')->constrained()->cascadeOnDelete();
                $table->string('locale', 5);
                $table->string('slug');
                $table->timestamps();
                $table->unique(['locale', 'slug']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('category_redirects');
        Schema::dropIfExists('category_product');
        Schema::table('categories', fn (Blueprint $table) => $table->dropConstrainedForeignId('parent_id'));
    }
};
