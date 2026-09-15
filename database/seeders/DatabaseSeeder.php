<?php

namespace Database\Seeders;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Module seeders only add missing rows, so this is safe on staging and production.
     * Model events stay on: a new variant records its starting price in price_history.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
