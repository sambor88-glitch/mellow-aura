<?php

namespace Tests\Feature\Content;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_kasia_tells_about_herself_and_the_materials_from_the_panel(): void
    {
        Setting::query()->where('key', 'text_about_paragraph_3')->update(['value' => json_encode(null)]);
        $category = Category::factory()->create();

        foreach (['Wazony', 'Patery', 'Talerze', 'Kadzielnice', 'Scrunchies'] as $position => $name) {
            $product = Product::factory()->create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'category_id' => $category->id, 'sort_order' => $position + 1]);
            ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 2]);
        }

        $this->get('/o-mnie')
            ->assertOk()
            ->assertSee('<title>O mnie — Kasia Samborska, pracownia MellowAura | Kraków</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('content.about').'">', false)
            ->assertSee('Cześć,<br>jestem <em class="text-brown italic">Kasia</em>', false)
            ->assertSeeInOrder(['Tworzę MellowAura – kameralną pracownię', 'Zajmuję się ceramiką i rękodziełem tekstylnym'])
            ->assertDontSee('Lubię łączyć surowość z miękkością, prostotę z symboliką')
            ->assertSee('href="'.route('content.contact').'"', false)
            ->assertSeeInOrder(['<dt class="mb-2 font-serif text-[21px]">Glina</dt>', 'Kamionka i szamot piaskowy', 'Rośliny'], false)
            ->assertDontSee('Temperatura wypału')
            ->assertSeeInOrder(['Niektóre z moich prac', 'Wazony', 'Patery', 'Talerze', 'Kadzielnice'])
            ->assertDontSee('Scrunchies');
    }

    public function test_the_header_and_the_home_page_lead_here(): void
    {
        $this->get('/')->assertSee('href="'.route('content.about').'"', false)->assertSee('Poznaj mnie bliżej');

        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('content.about'), '/').'"\s+aria-current="page"/',
            $this->get('/o-mnie')->getContent(),
        );
    }

    public function test_an_empty_shop_leaves_out_the_works(): void
    {
        $this->get('/o-mnie')
            ->assertOk()
            ->assertDontSee('Niektóre z moich prac');
    }
}
