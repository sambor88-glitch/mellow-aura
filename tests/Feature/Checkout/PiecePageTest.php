<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\IssueCertificates;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\CertificatePdf;
use App\Modules\Checkout\Support\PieceCardPdf;
use App\Modules\Checkout\Support\PieceToken;
use App\Modules\Settings\Models\Setting;
use App\Modules\Shared\Support\QrCode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The page of one bought piece behind the QR code in the parcel (MA-234).
 */
class PiecePageTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'app/Modules/Checkout/Database/Migrations/2026_10_28_180000_add_piece_page_to_order_items_table.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_every_item_gets_its_own_token_that_cannot_be_guessed(): void
    {
        $order = $this->paidOrder();
        $tokens = $order->items->pluck('page_token');

        $this->assertCount($tokens->count(), $tokens->unique());
        foreach ($tokens as $token) {
            $this->assertMatchesRegularExpression('/^'.PieceToken::PATTERN.'$/', $token);
        }
        // Not the id, not the order number, not sequential: two items of one order share nothing guessable.
        $this->assertStringNotContainsString((string) $order->items->first()->id, $tokens->first());
        $this->assertStringNotContainsString((string) $order->number, $tokens->first());
        $this->assertGreaterThan(1e17, strlen(PieceToken::ALPHABET) ** PieceToken::LENGTH);
        $this->assertStringNotContainsString('0', PieceToken::ALPHABET.'1lio');
    }

    public function test_the_page_tells_about_the_piece_and_nothing_about_the_buyer(): void
    {
        Setting::create(['key' => 'care_rule_ceramics', 'value' => 'Zmywarka tak, złoto tylko ręcznie']);
        Setting::create(['key' => 'instagram_handle', 'value' => 'mellowaura']);
        $order = $this->paidOrder();
        $vase = $order->items->firstWhere('product_name', 'Wazon z paprocią');
        $vase->update(['fired_on' => '2026-10-12', 'glaze' => 'Biel cynowa', 'plant' => 'Paproć spod pracowni']);

        $response = $this->get('/k/'.$vase->page_token)
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSeeInOrder(['Wazon z paprocią', 'Niski 16 cm', 'Data wypału', '12 października 2026', 'Szkliwo', 'Biel cynowa', 'Roślina', 'Paproć spod pracowni'])
            ->assertSee('Zmywarka tak, złoto tylko ręcznie')
            ->assertSee('href="'.route('custom-orders.index').'#formularz"', false)
            ->assertSee('Zrób mi podobny')
            ->assertSee('href="'.route('shop.index').'"', false)
            ->assertSee('@mellowaura');

        // The city stays out too: the site's own details say „Kraków”, so the street and postal code stand in for it.
        foreach (['Anna Nowak', 'ania@example.com', '600100200', '600 100 200', $order->number, 'Długa 1', '31-001', '100,00', '500,00'] as $personal) {
            $response->assertDontSee($personal, false);
        }
    }

    public function test_empty_studio_fields_do_not_show(): void
    {
        $order = $this->paidOrder();

        $this->get('/k/'.$order->items->firstWhere('product_name', 'Wazon z paprocią')->page_token)
            ->assertOk()
            ->assertDontSee('Data wypału')
            ->assertDontSee('Szkliwo')
            ->assertDontSee('Roślina');
    }

    public function test_the_mug_text_is_escaped(): void
    {
        $order = $this->paidOrder();
        $mug = $order->items->firstWhere('product_name', 'Kubek z napisem');

        $this->get('/k/'.$mug->page_token)
            ->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_a_wrong_token_an_unpaid_order_a_voucher_and_the_wrapping_are_not_found(): void
    {
        $order = $this->paidOrder();

        $this->get('/k/abcdefghjkmn')->assertNotFound();
        $this->get('/k/ABCDEFGHJKMN')->assertNotFound();
        $this->get('/k/'.$order->items->first()->id)->assertNotFound();
        $this->get('/k/'.$order->items->firstWhere('product_name', 'Voucher kwotowy')->page_token)->assertNotFound();
        $this->get('/k/'.$order->items->firstWhere('product_name', 'Pakowanie na prezent')->page_token)->assertNotFound();
        $this->get('/k/'.$this->paidOrder(PaymentStatus::Pending)->items->first()->page_token)->assertNotFound();
    }

    public function test_guessing_tokens_in_bulk_is_slowed_down(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->get('/k/'.PieceToken::generate())->assertNotFound();
        }

        $this->get('/k/'.PieceToken::generate())->assertTooManyRequests();
    }

    public function test_the_page_speaks_the_language_of_the_order(): void
    {
        $order = $this->paidOrder();
        $order->update(['locale' => 'en', 'currency' => 'EUR']);

        $this->get('/k/'.$order->items->firstWhere('product_name', 'Wazon z paprocią')->page_token)
            ->assertOk()
            ->assertSee('<html lang="en"', false)
            ->assertSee('Make me a similar one')
            ->assertSee('href="'.url('/en/shop').'"', false);
    }

    public function test_the_page_stays_out_of_the_sitemap(): void
    {
        $order = $this->paidOrder();

        $this->get('/sitemap.xml')->assertOk()->assertDontSee('/k/'.$order->items->first()->page_token);
    }

    public function test_items_already_saved_get_a_token(): void
    {
        $order = $this->paidOrder();
        $migration = require base_path(self::MIGRATION);
        $migration->down();
        DB::table('order_items')->insert(['order_id' => $order->id, 'product_name' => 'Wazon', 'variant_label' => '', 'quantity' => 1, 'unit_price_gross' => 3900, 'created_at' => now(), 'updated_at' => now()]);

        $migration->up();

        $tokens = DB::table('order_items')->pluck('page_token');
        $this->assertCount(5, $tokens->filter()->unique());
    }

    public function test_the_panel_saves_the_studio_fields_and_prints_the_card(): void
    {
        $order = $this->paidOrder();
        $vase = $order->items->firstWhere('product_name', 'Wazon z paprocią');
        $voucher = $order->items->firstWhere('product_name', 'Voucher kwotowy');
        $this->actingAs(User::factory()->create());

        $this->get('/panel/zamowienia/'.$order->number)
            ->assertOk()
            ->assertSee('href="'.$vase->pageUrl().'"', false)
            ->assertSee('href="'.route('admin.orders.piece-card', [$order, $vase]).'"', false)
            ->assertDontSee('href="'.$voucher->pageUrl().'"', false)
            ->assertSee('Dopisz datę wypału i szkliwo');

        $this->patch(route('admin.orders.piece', [$order, $vase]), ['fired_on' => '2026-10-12', 'glaze' => ' Biel cynowa ', 'plant' => ''])
            ->assertRedirect(route('admin.orders.show', $order).'#sztuka-'.$vase->id);
        $vase->refresh();
        $this->assertSame('2026-10-12', $vase->fired_on->toDateString());
        $this->assertSame('Biel cynowa', $vase->glaze);
        $this->assertNull($vase->plant);

        $this->patch(route('admin.orders.piece', [$order, $vase]), ['fired_on' => now()->addWeek()->toDateString(), 'glaze' => str_repeat('a', 121)])
            ->assertSessionHasErrors(['fired_on', 'glaze'], null, 'piece'.$vase->id);
        $this->assertSame('Biel cynowa', $vase->fresh()->glaze);

        $this->patch(route('admin.orders.piece', [$order, $voucher]), ['glaze' => 'x'])->assertNotFound();
        $other = $this->paidOrder();
        $this->patch(route('admin.orders.piece', [$other, $vase]), ['glaze' => 'x'])->assertNotFound();

        $card = $this->get(route('admin.orders.piece-card', [$order, $vase]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $card->getContent());
        $this->get(route('admin.orders.piece-card', [$order, $voucher]))->assertNotFound();

        auth()->logout();
        $this->get(route('admin.orders.piece-card', [$order, $vase]))->assertRedirect('/panel/logowanie');
    }

    public function test_the_card_and_the_certificate_carry_the_same_code(): void
    {
        $order = $this->paidOrder();
        $vase = $order->items->firstWhere('product_name', 'Wazon z paprocią');
        $vase->update(['fired_on' => '2026-10-12', 'glaze' => 'Biel cynowa']);
        $qr = QrCode::dataUri($vase->pageUrl());

        $card = app(PieceCardPdf::class)->html($vase->fresh('order'));
        $this->assertStringContainsString($qr, $card);
        $this->assertStringContainsString(preg_replace('#^https?://#', '', $vase->pageUrl()), $card);
        $this->assertStringContainsString('Wazon z paprocią · Niski 16 cm', $card);
        $this->assertStringNotContainsString('Anna Nowak', $card);

        $certificate = app(CertificatePdf::class)->html(app(IssueCertificates::class)($order));
        $this->assertStringContainsString($qr, $certificate);
        $this->assertStringContainsString('12.10.2026', $certificate);
        $this->assertStringContainsString('Biel cynowa', $certificate);
        $this->assertStringContainsString('podpis', $certificate);
    }

    private function paidOrder(PaymentStatus $payment = PaymentStatus::Paid): Order
    {
        $ceramics = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $vouchers = Category::factory()->create(['group' => CategoryGroup::Workshops]);
        $vase = ProductVariant::factory()->for(Product::factory()->create(['category_id' => $ceramics->id, 'care_note' => null]))->create(['label' => 'Niski 16 cm']);
        $voucher = ProductVariant::factory()->for(Product::factory()->create(['category_id' => $vouchers->id]))->create();

        $order = Order::create([
            'status' => $payment === PaymentStatus::Paid ? OrderStatus::InProgress : OrderStatus::New,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600100200',
            'shipping_method' => 'courier',
            'shipping_address' => ['street' => 'Długa 1', 'postal_code' => '31-001', 'city' => 'Kraków'],
            'shipping_gross' => 1600,
            'total_gross' => 50000,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => $payment,
            'paid_at' => $payment === PaymentStatus::Paid ? now() : null,
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);

        foreach ([
            ['product_variant_id' => $vase->id, 'product_name' => 'Wazon z paprocią', 'variant_label' => 'Niski 16 cm', 'quantity' => 1],
            ['product_variant_id' => $voucher->id, 'product_name' => 'Voucher kwotowy', 'variant_label' => '250 zł', 'quantity' => 1],
            ['product_variant_id' => null, 'product_name' => 'Pakowanie na prezent', 'variant_label' => '', 'quantity' => 1, 'is_made_to_order' => true],
            ['product_variant_id' => null, 'product_name' => 'Kubek z napisem', 'variant_label' => 'Duży', 'quantity' => 1, 'is_made_to_order' => true, 'custom_text' => '<script>alert(1)</script>'],
        ] as $item) {
            $order->items()->create(['unit_price_gross' => 10000, ...$item]);
        }

        return $order->load('items');
    }
}
