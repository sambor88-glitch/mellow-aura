<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Actions\ConvertEuroPrices;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\ExchangeRate;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EuroRateTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_price_is_rounded_to_the_nearest_whole_euro_ending_in_ninety_cents(): void
    {
        // 239 zł at 4.2653 is €56.03, 69 zł is €16.18, 1 zł is €0.23.
        $this->assertSame([5590, 1590, 90], [
            ConvertEuroPrices::euro(23900, 42653),
            ConvertEuroPrices::euro(6900, 42653),
            ConvertEuroPrices::euro(100, 42653),
        ]);
    }

    public function test_the_command_stores_the_nbp_rate_and_moves_only_prices_that_follow_it(): void
    {
        $this->fakeNbp('4.2653', '2026-09-24');
        [$following, $typed] = [$this->variant(23900, true), $this->variant(23900, false)];
        $typed->prices()->create(['currency' => 'EUR', 'amount_minor' => 5000]);

        $this->artisan('catalog:euro-rate')->assertSuccessful();

        $rate = ExchangeRate::for('EUR');
        $this->assertSame(['4.2653', '2026-09-24'], [$rate->rate, $rate->effective_on->toDateString()]);
        $this->assertSame([5590, 5000], [$following->price('EUR'), $typed->fresh()->price('EUR')]);
        $this->assertSame([5590], $following->priceHistory()->where('currency', 'EUR')->pluck('price_gross')->all());
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
        $this->assertSame([5590, 5990], $variant->priceHistory()->where('currency', 'EUR')->orderBy('id')->pluck('price_gross')->all());
    }

    public function test_without_an_answer_from_nbp_the_prices_stay_at_the_last_rate(): void
    {
        $variant = $this->variant(23900, true);
        $this->fakeNbp('4.2653', '2026-09-24');
        $this->artisan('catalog:euro-rate')->assertSuccessful();

        Http::swap(new Factory);
        Http::fake(['api.nbp.pl/*' => Http::response('', 500)]);

        $this->artisan('catalog:euro-rate')->assertFailed();
        $this->assertSame(5590, $variant->fresh()->price('EUR'));
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
        $this->assertSame(6290, $variant->fresh()->price('EUR'));
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
