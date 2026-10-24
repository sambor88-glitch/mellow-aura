<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OfferCheckPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_lists_the_seeded_offer(): void
    {
        Storage::fake('public');
        $this->seed(CatalogSeeder::class);

        $this->get('/')
            ->assertOk()
            ->assertSee('Produktów: 17, opublikowanych: 13.')
            ->assertSee('Zestaw 4 filiżanek z talerzykami');
    }
}
