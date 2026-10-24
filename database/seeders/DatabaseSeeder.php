<?php

namespace Database\Seeders;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Model events stay on: every variant price has to land in price_history.
     */
    public function run(): void
    {
        $this->call([
            SettingsSeeder::class,
            CatalogSeeder::class,
        ]);
    }
}
