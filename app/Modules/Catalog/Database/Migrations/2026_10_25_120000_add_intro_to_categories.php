<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A short introduction above a category's products, written in the panel next to its title and description
 * for Google. Empty means no introduction on the page.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL does not roll back ALTER TABLE, so a deploy that failed halfway may have left one
        // of the columns behind without recording the migration. Add only what is missing.
        foreach (['categories', 'category_translations'] as $tableName) {
            if (! Schema::hasColumn($tableName, 'intro')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->text('intro')->nullable()->after('seo_description');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::table('category_translations', fn (Blueprint $table) => $table->dropColumn('intro'));
        Schema::table('categories', fn (Blueprint $table) => $table->dropColumn('intro'));
    }
};
