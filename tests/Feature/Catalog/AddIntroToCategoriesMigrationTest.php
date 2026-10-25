<?php

namespace Tests\Feature\Catalog;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AddIntroToCategoriesMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_finishes_when_a_failed_deploy_left_the_column_behind(): void
    {
        $migration = require base_path('app/Modules/Catalog/Database/Migrations/2026_10_25_120000_add_intro_to_categories.php');

        Schema::table('category_translations', fn ($table) => $table->dropColumn('intro'));

        $migration->up();

        $this->assertTrue(Schema::hasColumn('categories', 'intro'));
        $this->assertTrue(Schema::hasColumn('category_translations', 'intro'));
    }
}
