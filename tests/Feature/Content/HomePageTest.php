<?php

namespace Tests\Feature\Content;

use App\Modules\Catalog\Database\Seeders\CatalogSeeder;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Actions\UpdateSetting;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['staging.password' => '']);
        Storage::fake('public');
        $this->seed([SettingsSeeder::class, CatalogSeeder::class]);
    }

    public function test_home_shows_the_prototype_hero_and_the_first_four_products_in_stock(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<h1 class="ma-hero__title">Glina i jedwab<br>z <em>jednej pary</em> rąk.</h1>', false)
            ->assertSeeInOrder(['Wazony', 'Zestaw 4 filiżanek z talerzykami', 'Patery', 'Talerze'])
            ->assertSee('href="'.url('/produkt/wazony').'"', false)
            ->assertDontSee('Biżuteria</span>', false);
    }

    public function test_sold_out_and_draft_products_are_left_out(): void
    {
        ProductVariant::query()
            ->whereIn('product_id', Product::query()->where('slug', 'wazony')->select('id'))
            ->update(['stock' => 0]);

        $this->get('/')
            ->assertDontSee('href="'.url('/produkt/wazony').'"', false)
            ->assertSee('Kubki z cytatem');
    }

    public function test_facts_come_from_the_panel_and_empty_ones_are_hidden(): void
    {
        $this->get('/')
            ->assertSee('3–5 dni')
            ->assertSee('Lubię łączyć surowość z miękkością')
            ->assertSee('od 69,00');

        app(UpdateSetting::class)->handle('shipping.dispatch_time', '');
        app(UpdateSetting::class)->handle('services.scarf_price_from', '');

        $this->get('/')
            ->assertDontSee('wysyłka zamówienia')
            ->assertDontSee('od 69,00');
    }

    public function test_struck_through_price_shows_the_lowest_price_from_30_days(): void
    {
        $this->travelTo(now()->subDays(40));
        $variant = ProductVariant::factory()
            ->for(Product::factory()->state(['name' => 'Misa testowa', 'sort_order' => 0]))
            ->create(['price_gross' => 12000]);
        $this->travelBack();

        $variant->update(['price_gross' => 9000, 'compare_at_price' => 15000]);

        $this->get('/')
            ->assertSeeInOrder(['promocja', 'Misa testowa', '90,00', '150,00', 'Najniższa cena z 30 dni przed obniżką: 120,00']);
    }

    public function test_pages_link_with_hrefs_not_click_handlers(): void
    {
        $this->get('/')
            ->assertSee('href="'.url('/kubek-z-napisem').'"', false)
            ->assertSee('href="'.url('/z-twojej-apaszki').'"', false)
            ->assertDontSee('onClick', false);
    }
}
