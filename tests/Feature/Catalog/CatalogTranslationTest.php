<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class CatalogTranslationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_a_product_reads_in_the_language_of_the_page_and_never_falls_back_to_polish(): void
    {
        [$translated, $polishOnly] = $this->twoProducts();

        App::setLocale('en');
        $this->assertSame('Rough-edged bowl', $translated->name);
        $this->assertSame('rough-edged-bowl', $translated->slug);
        $this->assertSame('Small', $translated->variants->first()->label);
        $this->assertSame('Ceramics', $translated->category->name);
        $this->assertNull($polishOnly->name);

        App::setLocale('pl');
        $this->assertSame('Miska z surową krawędzią', $translated->name);
        $this->assertSame('Mała', $translated->variants->first()->label);
    }

    public function test_the_english_shop_lists_only_what_is_written_in_english(): void
    {
        [$translated, $polishOnly] = $this->twoProducts();

        $this->assertEquals([$translated->id], Product::query()->live()->translatedInto('en')->pluck('id')->all());

        App::setLocale('en');
        $this->assertEquals([$translated->id], Product::query()->live()->pluck('id')->all());

        App::setLocale('pl');
        $this->assertEqualsCanonicalizing([$translated->id, $polishOnly->id], Product::query()->live()->pluck('id')->all());
    }

    public function test_a_product_address_uses_the_slug_of_its_language(): void
    {
        [$translated, $polishOnly] = $this->twoProducts();

        $this->get('/en/product/rough-edged-bowl')->assertOk();
        $this->get('/produkt/miska-z-surowa-krawedzia')->assertOk();

        // The Polish slug under an English address, and a piece not written in English, do not exist in English.
        $this->get('/en/product/miska-z-surowa-krawedzia')->assertNotFound();
        $this->get('/en/product/'.$polishOnly->getRawOriginal('slug'))->assertNotFound();
    }

    /** @return array{Product, Product} */
    private function twoProducts(): array
    {
        $category = Category::factory()->create(['name' => 'Ceramika', 'slug' => 'ceramika']);
        $category->translations()->create(['locale' => 'en', 'name' => 'Ceramics', 'slug' => 'ceramics']);

        $translated = Product::factory()->create(['name' => 'Miska z surową krawędzią', 'slug' => 'miska-z-surowa-krawedzia', 'category_id' => $category->id]);
        $translated->translations()->create(['locale' => 'en', 'name' => 'Rough-edged bowl', 'slug' => 'rough-edged-bowl']);
        $variant = ProductVariant::factory()->create(['product_id' => $translated->id, 'label' => 'Mała', 'stock' => 2]);
        $variant->translations()->create(['locale' => 'en', 'label' => 'Small']);

        $polishOnly = Product::factory()->create(['name' => 'Voucher na warsztaty', 'slug' => 'voucher-na-warsztaty', 'category_id' => $category->id]);
        ProductVariant::factory()->create(['product_id' => $polishOnly->id, 'stock' => 1]);

        return [$translated->fresh(), $polishOnly->fresh()];
    }
}
