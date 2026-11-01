<?php

namespace Tests\Feature\Content;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * MA-116: service pages lead into the shop, products and gift pages lead to the services, and every link says
 * where it goes.
 */
class InternalLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();
        $this->seed(SettingsSeeder::class);

        foreach ([
            'kubki' => 'Kubki',
            'filizanki' => 'Filiżanki',
            'talerze' => 'Talerze',
            'miski' => 'Miski',
            'wazony' => 'Wazony',
            'patery' => 'Patery',
            'scrunchie' => 'Scrunchie',
            'opaski' => 'Opaski',
            'kosmetyczki' => 'Kosmetyczki',
        ] as $slug => $name) {
            Category::factory()->create(['slug' => $slug, 'name' => $name, 'group' => CategoryGroup::Ceramics]);
        }
    }

    public function test_each_service_page_leads_to_its_shop_categories(): void
    {
        $this->get('/odcisk-twojej-rosliny')
            ->assertOk()
            ->assertSee('href="'.route('shop.category', 'talerze').'"', false)
            ->assertSeeInOrder(['Talerze w sklepie', 'Miski w sklepie', 'Wazony w sklepie', 'Patery w sklepie']);

        $this->get('/z-twojej-apaszki')
            ->assertOk()
            ->assertSeeInOrder(['Scrunchie w sklepie', 'Opaski w sklepie', 'Kosmetyczki w sklepie']);

        $this->get('/kubek-z-napisem')
            ->assertOk()
            ->assertSee('Kubki w sklepie')
            ->assertSee('Filiżanki w sklepie');
    }

    public function test_a_removed_category_drops_out_instead_of_leading_nowhere(): void
    {
        Category::where('slug', 'wazony')->delete();

        $this->get('/odcisk-twojej-rosliny')
            ->assertSee('Talerze w sklepie')
            ->assertDontSee('Wazony w sklepie');
    }

    public function test_a_product_from_a_service_category_leads_to_that_service(): void
    {
        $plate = Product::factory()->create([
            'name' => 'Talerz z paprocią',
            'slug' => 'talerz-z-paprocia',
            'category_id' => Category::where('slug', 'talerze')->value('id'),
            'is_published' => true,
        ]);
        ProductVariant::factory()->create(['product_id' => $plate->id, 'price_gross' => 14900, 'stock' => 1]);

        $this->get('/produkt/talerz-z-paprocia')
            ->assertOk()
            ->assertSee('Zrobię taki z Twojego')
            ->assertSee('href="'.route('content.imprint').'"', false)
            ->assertSee('Odcisk Twojej rośliny — odcisnę ją w glinie na talerzu');
    }

    public function test_gift_pages_lead_to_the_services(): void
    {
        foreach (['/prezenty', '/zestawy-prezentowe'] as $page) {
            $this->get($page)
                ->assertOk()
                ->assertSee('Prezent, którego nie kupisz gotowego')
                ->assertSee('href="'.route('mug.index').'"', false)
                ->assertSee('href="'.route('content.imprint').'"', false)
                ->assertSee('href="'.route('content.scarf').'"', false);
        }
    }

    public function test_no_link_says_click_here_or_see_more(): void
    {
        foreach (['/odcisk-twojej-rosliny', '/z-twojej-apaszki', '/kubek-z-napisem', '/prezenty', '/zestawy-prezentowe'] as $page) {
            $html = mb_strtolower($this->get($page)->getContent());

            $this->assertStringNotContainsString('kliknij tutaj', $html, $page);
            $this->assertStringNotContainsString('zobacz więcej', $html, $page);
        }
    }
}
