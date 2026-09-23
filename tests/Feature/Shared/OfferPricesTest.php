<?php

namespace Tests\Feature\Shared;

use App\Modules\Shared\Models\OfferPriceHistory;
use App\Modules\Shared\Support\OfferPrices;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferPricesTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_save_records_only_changed_prices_and_the_price_before_a_first_change(): void
    {
        $prices = app(OfferPrices::class);

        $prices->record('kiln_prices', ['bisque' => 4000, 'shelf' => 18000], ['bisque' => 3500, 'shelf' => 18000]);
        $this->assertSame(
            [['kiln_prices:bisque', 4000], ['kiln_prices:bisque', 3500], ['kiln_prices:shelf', 18000]],
            OfferPriceHistory::query()->orderBy('offer')->orderBy('id')->get()->map(fn (OfferPriceHistory $row) => [$row->offer, $row->price_gross])->all(),
        );

        $prices->record('kiln_prices', ['bisque' => 3500, 'shelf' => 18000], ['bisque' => 3500, 'shelf' => 18000]);
        $this->assertSame(3, OfferPriceHistory::count());
    }

    public function test_the_lowest_price_looks_30_days_back_from_the_reduction(): void
    {
        $prices = app(OfferPrices::class);
        $this->travelTo(now()->setDate(2026, 9, 1));
        $prices->record('workshop_types', [], ['couples' => 39000]);
        $this->travelTo(now()->setDate(2026, 10, 10));
        $prices->record('workshop_types', [], ['couples' => 36000]);
        $this->travelTo(now()->setDate(2026, 11, 20));
        $prices->record('workshop_types', [], ['couples' => 32000]);

        $row = fn (int $price, ?int $was) => collect([['code' => 'couples', 'price_gross' => $price, 'compare_at_price' => $was]]);
        $key = fn (array $row) => $row['code'];

        // 36 000 was in effect when the 30 days before 20 November began; 39 000 ended before them.
        $this->assertSame([39000, 36000], [$prices->withReductions('workshop_types', $row(32000, 39000), $key)->first()['was'], $prices->withReductions('workshop_types', $row(32000, 39000), $key)->first()['lowest']]);

        // No crossed-out price without a higher price before the reduction, or when the price didn't come through the panel.
        $this->assertNull($prices->withReductions('workshop_types', $row(32000, 30000), $key)->first()['lowest']);
        $this->assertNull($prices->withReductions('workshop_types', $row(29000, 39000), $key)->first()['was']);
        $this->assertNull($prices->withReductions('workshop_types', collect([['code' => 'new', 'price_gross' => 1000, 'compare_at_price' => 2000]]), $key)->first()['lowest']);
    }
}
