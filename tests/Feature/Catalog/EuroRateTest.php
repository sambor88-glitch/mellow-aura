<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Actions\ConvertEuroPrices;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ExchangeRate;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EuroRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_price_is_rounded_up_to_the_next_price_ending_in_ninety_cents(): void
    {
        // 239 zł at 4.2653 is €56.03, 69 zł is €16.18, 1 zł is €0.23, 239.60 zł at 4.0000 is exactly €59.90.
        $this->assertSame([5690, 1690, 90, 5990], [
            ConvertEuroPrices::euro(23900, 42653),
            ConvertEuroPrices::euro(6900, 42653),
            ConvertEuroPrices::euro(100, 42653),
            ConvertEuroPrices::euro(23960, 40000),
        ]);
    }

    public function test_the_markup_goes_on_top_of_the_rate_before_rounding(): void
    {
        // 239 zł at 4.2653 plus 15% is €64.44, 69 zł plus 15% is €18.60.
        $this->assertSame([6490, 1890], [ConvertEuroPrices::euro(23900, 42653, 15), ConvertEuroPrices::euro(6900, 42653, 15)]);
    }

    public function test_the_command_counts_the_markup_from_the_panel(): void
    {
        Setting::query()->create(['key' => ConvertEuroPrices::MARKUP, 'value' => 15]);
        $variant = $this->variant(23900, true);
        $this->fakeNbp('4.2653', '2026-09-24');

        $this->artisan('catalog:euro-rate')->assertSuccessful();

        $this->assertSame(6490, $variant->price('EUR'));
    }

    public function test_saving_the_markup_in_the_panel_recounts_the_euro_prices_at_once(): void
    {
        ExchangeRate::create(['currency' => 'EUR', 'rate' => '4.2653', 'effective_on' => '2026-09-24']);
        [$following, $typed] = [$this->variant(23900, true), $this->variant(23900, false)];
        $typed->prices()->create(['currency' => 'EUR', 'amount_minor' => 5000]);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put('/panel/produkty/ceny-w-euro', ['euro_markup_percent' => '15'])
            ->assertRedirect(route('admin.products.index').'#ceny-w-euro')
            ->assertSessionHasNoErrors();

        $this->assertSame([6490, 5000], [$following->price('EUR'), $typed->fresh()->price('EUR')]);
        $this->actingAs($user)->get('/panel/produkty')->assertSee('value="15"', false)->assertSee('z narzutem 15%');

        $this->actingAs($user)->put('/panel/produkty/ceny-w-euro', ['euro_markup_percent' => ''])->assertSessionHasNoErrors();
        $this->assertSame(5690, $following->fresh()->price('EUR'));

        $this->actingAs($user)->put('/panel/produkty/ceny-w-euro', ['euro_markup_percent' => '15,5'])
            ->assertSessionHasErrors(['euro_markup_percent' => 'Wpisz pełną liczbę procent, np. 15']);
    }

    public function test_the_command_stores_the_nbp_rate_and_moves_only_prices_that_follow_it(): void
    {
        $this->fakeNbp('4.2653', '2026-09-24');
        [$following, $typed] = [$this->variant(23900, true), $this->variant(23900, false)];
        $typed->prices()->create(['currency' => 'EUR', 'amount_minor' => 5000]);

        $this->artisan('catalog:euro-rate')->assertSuccessful();

        $rate = ExchangeRate::for('EUR');
        $this->assertSame(['4.2653', '2026-09-24'], [$rate->rate, $rate->effective_on->toDateString()]);
        $this->assertSame([5690, 5000], [$following->price('EUR'), $typed->fresh()->price('EUR')]);
        $this->assertSame([5690], $following->priceHistory()->where('currency', 'EUR')->pluck('price_gross')->all());
    }

    public function test_a_new_rate_changes_the_price_and_its_history_and_the_same_rate_changes_nothing(): void
    {
        $variant = $this->variant(23900, true, compareAt: 29900);

        $this->fakeNbp('4.2653', '2026-09-24');
        $this->artisan('catalog:euro-rate')->assertSuccessful();
        $this->artisan('catalog:euro-rate')->assertSuccessful();

        $this->fakeNbp('4.0000', '2026-09-25');
        $this->artisan('catalog:euro-rate')->assertSuccessful();

        $variant->refresh();
        $this->assertSame([5990, 7490], [$variant->price('EUR'), $variant->compareAtPrice('EUR')]);
        $this->assertSame([5690, 5990], $variant->priceHistory()->where('currency', 'EUR')->orderBy('id')->pluck('price_gross')->all());
    }

    public function test_without_an_answer_from_nbp_the_prices_stay_at_the_last_rate(): void
    {
        $variant = $this->variant(23900, true);
        $this->fakeNbp('4.2653', '2026-09-24');
        $this->artisan('catalog:euro-rate')->assertSuccessful();

        Http::swap(new Factory);
        Http::fake(['api.nbp.pl/*' => Http::response('', 500)]);

        $this->artisan('catalog:euro-rate')->assertFailed();
        $this->assertSame(5690, $variant->fresh()->price('EUR'));
    }

    public function test_the_panel_counts_the_euro_price_from_the_rate_instead_of_the_typed_one(): void
    {
        ExchangeRate::create(['currency' => 'EUR', 'rate' => '4.2653', 'effective_on' => '2026-09-24']);
        $variant = $this->variant(23900, false);
        $product = $variant->product;

        $this->actingAs(User::factory()->create())
            ->put('/panel/produkty/'.$product->id, [
                'form' => 'produkt-'.$product->id,
                'name' => $product->name,
                'category_id' => $product->category_id,
                'is_published' => '1',
                'euro_from_rate' => '1',
                'variants' => [['id' => $variant->id, 'label' => '', 'price' => '269', 'price_eur' => '10', 'stock' => '']],
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue($product->fresh()->euro_from_rate);
        $this->assertSame(6390, $variant->fresh()->price('EUR'));
    }

    private function variant(int $price, bool $followsRate, ?int $compareAt = null): ProductVariant
    {
        $category = Category::query()->first() ?? Category::factory()->create();
        $product = Product::factory()->create(['category_id' => $category->id, 'euro_from_rate' => $followsRate]);

        return ProductVariant::factory()->create(['product_id' => $product->id, 'label' => '', 'price_gross' => $price, 'compare_at_price' => $compareAt, 'stock' => 3]);
    }

    private function fakeNbp(string $rate, string $date): void
    {
        Http::swap(new Factory);
        Http::fake(['api.nbp.pl/*' => Http::response(['table' => 'A', 'currency' => 'euro', 'code' => 'EUR', 'rates' => [['no' => '185/A/NBP/2026', 'effectiveDate' => $date, 'mid' => (float) $rate]]])]);
    }
}
