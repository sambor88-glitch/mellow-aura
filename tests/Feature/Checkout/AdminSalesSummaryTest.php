<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Enums\SalesPeriod;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\SalesSummary;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSalesSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 12:00'));
    }

    public function test_only_the_owner_opens_the_summary(): void
    {
        $this->get('/panel/sprzedaz')->assertRedirect('/panel/logowanie');
        $this->actingAs(User::factory()->helper()->create())->get('/panel/sprzedaz')->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get('/panel/sprzedaz')
            ->assertOk()
            ->assertSee('>Podsumowanie sprzedaży</a>', false);
    }

    public function test_an_empty_period_says_so(): void
    {
        $this->order(['paid_at' => now()->subMonths(3)]);

        $this->actingAs(User::factory()->create())
            ->get('/panel/sprzedaz')
            ->assertOk()
            ->assertSee('W tym okresie nic się jeszcze nie sprzedało')
            ->assertDontSee('Najlepiej się sprzedaje');
    }

    public function test_revenue_is_paid_less_refunds_and_compares_with_the_same_days_a_month_before(): void
    {
        $mug = $this->variant('Kubek Poranek');
        // Paid this month: 255 zł with delivery, and an order where one of two pieces had already gone (refund 120 zł).
        $this->order(['paid_at' => now()->subDays(2)], [[$mug, 1, 23900]]);
        $this->order(['total_gross' => 25600, 'paid_at' => now()->subDay()], [[$mug, 2, 12000, 1]]);
        // Not counted: unpaid, and paid on the 25th of October — past the same days of the last month.
        $this->order(['payment_status' => PaymentStatus::Pending, 'paid_at' => null], [[$mug, 1, 23900]]);
        $this->order(['total_gross' => 99900, 'paid_at' => CarbonImmutable::parse('2026-10-25 10:00')], [[$mug, 1, 98300]]);
        // The month before, within the same days: 100 zł.
        $this->order(['total_gross' => 10000, 'shipping_gross' => 0, 'paid_at' => CarbonImmutable::parse('2026-10-05 10:00')], [[$mug, 1, 10000]]);

        $summary = app(SalesSummary::class)->for(SalesPeriod::ThisMonth)['currencies'];

        $this->assertCount(1, $summary);
        $this->assertSame('PLN', $summary[0]['currency']);
        $this->assertSame(['revenue' => 39100, 'paid' => 51100, 'refunds' => 12000, 'orders' => 2, 'average' => 25550, 'shipping' => 3200], array_intersect_key($summary[0]['current'], array_flip(['revenue', 'paid', 'refunds', 'orders', 'average', 'shipping'])));
        $this->assertSame(10000, $summary[0]['previous']['revenue']);
        $this->assertSame([['name' => 'Kubek Poranek', 'pieces' => 2, 'amount' => 35900]], $summary[0]['current']['top']);

        $this->actingAs(User::factory()->create())
            ->get('/panel/sprzedaz')
            ->assertOk()
            ->assertSeeInOrder(['Ten miesiąc', '1 listopada – 20 listopada 2026', 'te same dni poprzedniego miesiąca'])
            ->assertSeeInOrder(['Przychód', '391 zł', '↑ 291%', 'było 100 zł', 'Wpłaty 511 zł, zwroty −120 zł'])
            ->assertSeeInOrder(['Zamówienia', '2', 'Średni koszyk', '255,50 zł', 'Wysyłki', '32 zł'])
            ->assertSee('Przychód dzień po dniu')
            ->assertSee('aria-label="środa, 18 listopada: 255 zł"', false)
            ->assertSee('aria-label="czwartek, 19 listopada: 136 zł"', false)
            ->assertSee('aria-label="piątek, 20 listopada: 0 zł"', false)
            ->assertSeeInOrder(['Najlepiej się sprzedaje', 'Kubek Poranek', '2 szt.', '359 zł']);
    }

    public function test_zloty_and_euro_are_never_added_up(): void
    {
        $mug = $this->variant('Kubek Poranek');
        $this->order(['paid_at' => now()->subDay()], [[$mug, 1, 23900]]);
        $this->order(['currency' => 'EUR', 'locale' => 'en', 'total_gross' => 6490, 'shipping_gross' => 1500, 'paid_at' => now()->subDay()], [[$mug, 1, 4990]]);

        $summary = app(SalesSummary::class)->for(SalesPeriod::ThisMonth)['currencies'];

        $this->assertSame(['PLN', 'EUR'], array_column($summary, 'currency'));
        $this->assertSame(25500, $summary[0]['current']['revenue']);
        $this->assertSame(6490, $summary[1]['current']['revenue']);

        $this->actingAs(User::factory()->create())
            ->get('/panel/sprzedaz')
            ->assertSeeInOrder(['W złotych', '255 zł', 'W euro', '64,90 €', '15 €'])
            ->assertDontSee('319,90');
    }

    public function test_workshops_and_vouchers_count_apart_from_the_best_sellers(): void
    {
        $workshopCategory = Category::factory()->create(['group' => CategoryGroup::Workshops]);
        $voucher = ProductVariant::factory()->create(['product_id' => Product::factory()->create(['name' => 'Voucher na warsztat', 'category_id' => $workshopCategory->id])->id]);
        $vase = $this->variant('Wazon Fala');
        $bowls = $this->variant('Miseczki');

        $this->order(['shipping_method' => 'email', 'shipping_gross' => 0, 'total_gross' => 50000, 'paid_at' => now()->subDay()], [[$voucher, 2, 25000]]);
        $this->order(['paid_at' => now()->subDay(), 'total_gross' => 40000], [[$vase, 1, 18000], [$bowls, 3, 6000]]);
        // Gift wrapping is not a product.
        $this->order(['paid_at' => now()->subDay(), 'total_gross' => 2500, 'shipping_gross' => 0], [[null, 1, 2500]]);
        $paid = $this->workshop(BookingStatus::Confirmed, 2, 36000);
        $cancelled = $this->workshop(BookingStatus::Cancelled, 1, 18000);

        $current = app(SalesSummary::class)->for(SalesPeriod::ThisMonth)['currencies'][0]['current'];

        $this->assertSame([
            ['name' => 'Miseczki', 'pieces' => 3, 'amount' => 18000],
            ['name' => 'Wazon Fala', 'pieces' => 1, 'amount' => 18000],
        ], $current['top']);
        $this->assertSame(['count' => 2, 'amount' => 50000], $current['vouchers']);
        // The cancelled booking goes back whole: its seat is counted, its money is not.
        $this->assertSame(['count' => 3, 'amount' => 36000], $current['workshops']);
        $this->assertSame(18000, $current['refunds']);
        $this->assertSame(5, $current['orders']);
        $this->assertNotNull($paid->workshopBooking);
        $this->assertNotNull($cancelled->workshopBooking);

        $this->actingAs(User::factory()->create())
            ->get('/panel/sprzedaz')
            ->assertSeeInOrder(['Najlepiej się sprzedaje', 'Miseczki', '3 szt.', 'Wazon Fala', 'Osobno', 'Warsztaty', '3 miejsca', '360 zł', 'Vouchery', '2 szt.', '500 zł']);
    }

    public function test_the_best_sellers_stop_at_five(): void
    {
        foreach (range(1, 7) as $pieces) {
            $this->order(['paid_at' => now()->subDay()], [[$this->variant('Produkt '.$pieces), $pieces, 1000]]);
        }

        $top = app(SalesSummary::class)->for(SalesPeriod::ThisMonth)['currencies'][0]['current']['top'];

        $this->assertSame(['Produkt 7', 'Produkt 6', 'Produkt 5', 'Produkt 4', 'Produkt 3'], array_column($top, 'name'));
    }

    public function test_each_period_has_its_range_and_its_bars(): void
    {
        $now = CarbonImmutable::now();
        $summary = app(SalesSummary::class);

        $this->assertSame(['2026-10-01', '2026-11-01'], array_map(fn ($date) => $date->toDateString(), SalesPeriod::LastMonth->range($now)));
        $this->assertSame(['2026-09-01', '2026-10-01'], array_map(fn ($date) => $date->toDateString(), SalesPeriod::LastMonth->previousRange($now)));
        $this->assertSame('2026-10-22', SalesPeriod::Last30Days->range($now)[0]->toDateString());
        $this->assertSame(['2026-01-01 00:00', '2025-11-20 12:00'], [SalesPeriod::ThisYear->range($now)[0]->format('Y-m-d H:i'), SalesPeriod::ThisYear->previousRange($now)[1]->format('Y-m-d H:i')]);
        // The 31st of March is compared with the whole of February, not with the start of March.
        $this->assertSame('2026-03-01', SalesPeriod::ThisMonth->previousRange(CarbonImmutable::parse('2026-03-31 18:00'))[1]->toDateString());

        $this->order(['paid_at' => now()->subDays(3)]);

        $this->assertSame([], $summary->for(SalesPeriod::LastMonth)['currencies']);
        $this->assertCount(30, $summary->for(SalesPeriod::Last30Days)['currencies'][0]['chart']);
        $this->assertCount(47, $summary->for(SalesPeriod::ThisYear)['currencies'][0]['chart']);

        $year = $this->actingAs(User::factory()->create())
            ->get('/panel/sprzedaz?period=year')
            ->assertOk()
            ->assertSee('Przychód tydzień po tygodniu')
            ->assertSee('aria-label="12.11–18.11: 255 zł"', false);
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>Ten rok<\/a>/', (string) $year->getContent());

        // An unknown period falls back to this month.
        $month = $this->get('/panel/sprzedaz?period=forever')->assertOk();
        $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>Ten miesiąc<\/a>/', (string) $month->getContent());
    }

    public function test_the_change_and_the_axis_read_as_round_numbers(): void
    {
        $this->assertSame('↑ 50%', SalesSummary::change(150, 100));
        $this->assertSame('↓ 25%', SalesSummary::change(75, 100));
        $this->assertSame('bez zmian', SalesSummary::change(100, 100));
        $this->assertSame('wcześniej 0', SalesSummary::change(100, 0));
        $this->assertSame('bez zmian', SalesSummary::change(0, 0));

        $this->assertSame(200000, SalesSummary::axisTop(123400));
        $this->assertSame(5000, SalesSummary::axisTop(5000));
        $this->assertSame(100000, SalesSummary::axisTop(100000));
        $this->assertSame(0, SalesSummary::axisTop(0));
    }

    private function variant(string $name): ProductVariant
    {
        return ProductVariant::factory()->create(['product_id' => Product::factory()->create(['name' => $name])->id]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{0: ?ProductVariant, 1: int, 2: int, 3?: int}>  $items  variant, quantity, unit price, missing
     */
    private function order(array $attributes = [], array $items = []): Order
    {
        $order = Order::create([
            'status' => OrderStatus::InProgress,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600100200',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M',
            'shipping_gross' => 1600,
            'total_gross' => 25500,
            'currency' => 'PLN',
            'locale' => 'pl',
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
            ...$attributes,
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);

        foreach ($items as $item) {
            [$variant, $quantity, $price] = $item;
            $order->items()->create([
                'product_variant_id' => $variant?->id,
                'product_name' => $variant?->product->name ?? 'Pakowanie na prezent',
                'variant_label' => $variant?->label ?? '',
                'quantity' => $quantity,
                'missing_quantity' => $item[3] ?? 0,
                'unit_price_gross' => $price,
                'currency' => $order->currency,
            ]);
        }

        return $order;
    }

    private function workshop(BookingStatus $status, int $seats, int $total): Order
    {
        $order = $this->order(['shipping_method' => 'email', 'shipping_gross' => 0, 'total_gross' => $total, 'paid_at' => now()->subDay()]);
        WorkshopBooking::query()->create([
            'workshop_date_id' => WorkshopDate::factory()->create(['starts_at' => now()->addWeek()])->id,
            'order_id' => $order->id,
            'seats' => $seats,
            'status' => $status,
            'expires_at' => now(),
        ]);

        return $order;
    }
}
