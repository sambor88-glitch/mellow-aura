<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
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

    public function test_the_switch_and_hreflang_lead_to_the_same_piece_in_the_other_language(): void
    {
        [$translated, $polishOnly] = $this->twoProducts();

        $this->get('/produkt/miska-z-surowa-krawedzia')
            ->assertSee('<link rel="alternate" hreflang="en" href="'.url('/en/product/rough-edged-bowl').'">', false)
            ->assertSee('href="'.url('/en/product/rough-edged-bowl').'" hreflang="en" lang="en"', false);

        // Built in Polish from an English page: the Polish address carries the Polish slug.
        $this->get('/en/product/rough-edged-bowl')
            ->assertSee('<link rel="alternate" hreflang="pl" href="'.url('/produkt/miska-z-surowa-krawedzia').'">', false)
            ->assertSee('href="'.url('/produkt/miska-z-surowa-krawedzia').'" hreflang="pl" lang="pl"', false);

        // Not written in English yet: no hreflang, and the switch leads to the English shop.
        $this->get('/produkt/'.$polishOnly->getRawOriginal('slug'))
            ->assertDontSee('<link rel="alternate" hreflang=', false)
            ->assertSee('href="'.url('/en/shop').'?unavailable=1" hreflang="en"', false);
    }

    public function test_the_english_shop_shows_english_pieces_in_english(): void
    {
        [$translated, $polishOnly] = $this->twoProducts();

        $this->get('/en/shop')
            ->assertOk()
            ->assertSee('<title>Shop — handmade ceramics and crafts | MellowAura</title>', false)
            ->assertSee('Rough-edged bowl')
            ->assertSee('href="'.url('/en/product/rough-edged-bowl').'"', false)
            ->assertSee('>Ceramics (1)</a>', false)
            ->assertSee('>All (1)</a>', false)
            ->assertSee('Price: low to high')
            ->assertSee('1 of 1 pieces on the shelf')
            ->assertDontSee('Voucher na warsztaty')
            ->assertDontSee('Miska z surową krawędzią');

        $this->get('/en/shop/ceramics')->assertOk()->assertSee('<link rel="canonical" href="'.url('/en/shop/ceramics').'">', false);
        $this->get('/en/shop/ceramika')->assertNotFound();

        $this->get('/sklep')
            ->assertSee('Miska z surową krawędzią')
            ->assertSee('Voucher na warsztaty')
            ->assertSee('Cena rosnąco');
    }

    public function test_the_english_product_page_is_in_english_without_złoty_only_offers(): void
    {
        [$translated] = $this->twoProducts();
        $translated->update(['care_note' => 'Zmywarka tak', 'deviation' => 'Złota krawędź', 'dimensions' => ['height_cm' => '9']]);
        $translated->translation('en')->update(['description' => 'Thrown by hand, rim left rough.', 'care_note' => 'Dishwasher safe']);
        $translated->variants()->create(['label' => 'Duża', 'price_gross' => 12900, 'stock' => 1])->translations()->create(['locale' => 'en', 'label' => 'Large']);
        Setting::query()->updateOrCreate(['key' => 'free_shipping_threshold'], ['value' => json_encode(30000)]);

        $this->get('/en/product/rough-edged-bowl')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Rough-edged bowl')
            ->assertSee('Thrown by hand, rim left rough.')
            ->assertSee('Dishwasher safe')
            ->assertSee('>Height</span>', false)
            ->assertSee('Size and price')
            ->assertSee('>Small</span>', false)
            ->assertSee('>Large</span>', false)
            ->assertSee('Add to basket')
            ->assertSee('Certificate of uniqueness')
            // No English deviation written: nothing shows, not the Polish one.
            ->assertDontSee('Złota krawędź')
            ->assertDontSee('Please note:')
            ->assertDontSee('BLIK')
            ->assertDontSee('free from')
            ->assertDontSee('Zmywarka tak');

        $this->get('/produkt/miska-z-surowa-krawedzia')
            ->assertSee('Rozmiar i cena')
            ->assertSee('Zmywarka tak')
            ->assertSee('Złota krawędź')
            ->assertSee('BLIK')
            ->assertSee('gratis od');
    }

    public function test_a_basket_filled_on_a_polish_page_still_names_its_pieces_on_an_english_one(): void
    {
        [, $polishOnly] = $this->twoProducts();

        $this->post('/koszyk', ['variant_id' => $polishOnly->variants->first()->id])->assertRedirect();

        // The drawer on an English page: the piece has no English name, so the basket names it in Polish.
        $this->get('/en/shop')->assertOk()->assertSee('Voucher na warsztaty');
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
