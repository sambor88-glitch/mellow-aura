<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Models\Product;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShopPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed([SettingsSeeder::class, CatalogSeeder::class]);
    }

    public function test_shop_lists_published_products_in_stock(): void
    {
        $this->get('/sklep')
            ->assertOk()
            ->assertSee('<title>Sklep — ceramika i rękodzieło handmade | MellowAura</title>', false)
            ->assertSee('Zestaw 4 filiżanek z talerzykami')
            ->assertDontSee('Kosmetyczki</a>', false)
            ->assertSee('13 z 13 produktów na półce');
    }

    public function test_sold_out_product_leaves_the_list(): void
    {
        Product::query()->where('slug', 'kadzielnice')->first()->variants()->update(['stock' => 0]);

        $this->get('/sklep')->assertOk()->assertDontSee('>Kadzielnice<', false);
    }

    public function test_category_page_has_its_own_address_title_and_breadcrumbs(): void
    {
        $this->get('/sklep/jedwab')
            ->assertOk()
            ->assertSee('<title>Jedwab — rękodzieło z Krakowa | MellowAura</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/sklep/jedwab').'">', false)
            ->assertSee('Jedwabne opaski')
            ->assertDontSee('Zestaw 4 filiżanek')
            ->assertSee('"@type":"BreadcrumbList"', false);
    }

    public function test_unknown_category_is_a_real_404(): void
    {
        $this->get('/sklep/nie-ma-takiej')->assertNotFound();
    }

    public function test_search_is_noindex_with_canonical_on_the_base_address(): void
    {
        $this->get('/sklep?q=kubki')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertSee('<link rel="canonical" href="'.url('/sklep').'">', false)
            ->assertSee('Kubki malowane ręcznie')
            ->assertDontSee('Jedwabne opaski');
    }

    public function test_search_without_results_shows_the_empty_state(): void
    {
        $this->get('/sklep?q=fortepian')
            ->assertOk()
            ->assertSee('Nic takiego teraz nie stoi na półce');
    }

    public function test_sort_by_price(): void
    {
        $this->get('/sklep?sort=price_asc')
            ->assertOk()
            ->assertSeeInOrder(['Scrunchies w 3 rozmiarach', 'Patery']);

        $this->get('/sklep?sort=price_desc')
            ->assertOk()
            ->assertSeeInOrder(['Patery', 'Scrunchies w 3 rozmiarach']);
    }

    public function test_sorted_list_keeps_the_base_canonical_and_stays_indexable(): void
    {
        $this->get('/sklep?sort=name')
            ->assertOk()
            ->assertSee('<link rel="canonical" href="'.url('/sklep').'">', false)
            ->assertDontSee('noindex');
    }

    public function test_unknown_address_shows_the_404_page_in_the_shop_layout(): void
    {
        $this->get('/nie-ma-takiej-strony')
            ->assertNotFound()
            ->assertSee('Tu nic nie stoi')
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }
}
