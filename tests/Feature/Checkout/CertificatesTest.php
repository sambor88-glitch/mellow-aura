<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Enums\FoodContact;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\IssueCertificates;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Certificate;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\CertificatePdf;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificatesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_every_handmade_piece_gets_a_number_once_and_vouchers_and_wrapping_get_none(): void
    {
        $order = $this->paidOrder();

        $certificates = app(IssueCertificates::class)($order);

        // Two vases, one scarf-silk scrunchie (one piece was gone from the shelf) and the configurator mug.
        $this->assertSame(
            [['Wazony', 1], ['Wazony', 2], ['Scrunchies', 1], ['Kubek z napisem', 1]],
            $certificates->map(fn (Certificate $certificate) => [$certificate->orderItem->product_name, $certificate->piece])->all(),
        );
        $this->assertMatchesRegularExpression('#^\d{4}/\d{4}$#', $certificates->first()->number);
        $this->assertSame($certificates->pluck('number')->unique()->count(), 4);

        // Printing again keeps the same numbers.
        $this->assertSame($certificates->pluck('number')->all(), app(IssueCertificates::class)($order->fresh())->pluck('number')->all());
        $this->assertSame(4, Certificate::count());
    }

    public function test_the_card_carries_the_piece_care_warnings_and_the_producer(): void
    {
        Setting::create(['key' => 'care_rule_ceramics', 'value' => 'Zmywarka tak, złoto tylko ręcznie']);
        Setting::create(['key' => 'company_name', 'value' => 'MellowAura Katarzyna Samborska']);
        Setting::create(['key' => 'company_address', 'value' => 'ul. Wirtualna 1, 30-001 Kraków']);
        Setting::create(['key' => 'contact_email', 'value' => 'kasia@mellow-aura.com']);
        Setting::create(['key' => 'instagram_handle', 'value' => 'mellowaura']);
        $order = $this->paidOrder();

        $html = app(CertificatePdf::class)->html(app(IssueCertificates::class)($order));

        $this->assertStringContainsString('Wazony · Niski 16 cm', $html);
        $this->assertStringContainsString('Wysokość 16 cm', $html);
        $this->assertStringContainsString('Data wypału', $html);
        $this->assertStringContainsString('Materiał', $html);
        $this->assertStringContainsString('„JESZCZE / NIE TERAZ”', $html);
        foreach (['Zmywarka tak, złoto tylko ręcznie', 'Nie — tylko dekoracja', 'Nie do zmywarki', 'Nie stawiaj na ogniu.', 'MellowAura Katarzyna Samborska', 'ul. Wirtualna 1, 30-001 Kraków', 'kasia@mellow-aura.com', '@mellowaura'] as $text) {
            $this->assertStringContainsString($text, $html);
        }
    }

    public function test_the_panel_opens_the_certificates_of_a_paid_order_as_a_pdf(): void
    {
        $order = $this->paidOrder();
        $owner = User::factory()->create();

        $this->get('/panel/zamowienia/'.$order->number.'/certyfikaty')->assertRedirect('/panel/logowanie');

        $this->actingAs($owner)
            ->get('/panel/zamowienia/'.$order->number)
            ->assertSee('href="'.route('admin.orders.certificates', $order).'"', false)
            ->assertSee('Certyfikaty do druku');

        $response = $this->get('/panel/zamowienia/'.$order->number.'/certyfikaty')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="certyfikaty-'.$order->number.'.pdf"');
        $this->assertStringStartsWith('%PDF', $response->getContent());

        $this->get('/panel/zamowienia/'.$order->number)
            ->assertSeeInOrder(['Wazony', 'Certyfikat:', Certificate::query()->orderBy('id')->value('number')]);

        $unpaid = $this->paidOrder(PaymentStatus::Pending);
        $this->get('/panel/zamowienia/'.$unpaid->number)->assertDontSee('Certyfikaty do druku');
        $this->get('/panel/zamowienia/'.$unpaid->number.'/certyfikaty')->assertNotFound();
    }

    private function paidOrder(PaymentStatus $payment = PaymentStatus::Paid): Order
    {
        $ceramics = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $silk = Category::factory()->create(['group' => CategoryGroup::Crafts]);
        $vouchers = Category::factory()->create(['group' => CategoryGroup::Workshops]);

        $vase = ProductVariant::factory()->for(Product::factory()->create([
            'category_id' => $ceramics->id,
            'dimensions' => ['height_cm' => '16'],
            'food_contact' => FoodContact::NotSuitable,
            'deviation' => 'Nie do zmywarki',
            'safety_warnings' => 'Nie stawiaj na ogniu.',
        ]))->create(['label' => 'Niski 16 cm']);
        $scrunchie = ProductVariant::factory()->for(Product::factory()->create(['category_id' => $silk->id]))->create();
        $voucher = ProductVariant::factory()->for(Product::factory()->create(['category_id' => $vouchers->id]))->create();

        $order = Order::create([
            'status' => $payment === PaymentStatus::Paid ? OrderStatus::InProgress : OrderStatus::New,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600100200',
            'shipping_method' => 'parcel_locker',
            'shipping_gross' => 1600,
            'total_gross' => 50000,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => $payment,
            'paid_at' => $payment === PaymentStatus::Paid ? now() : null,
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);

        foreach ([
            ['product_variant_id' => $vase->id, 'product_name' => 'Wazony', 'variant_label' => 'Niski 16 cm', 'quantity' => 2],
            ['product_variant_id' => $scrunchie->id, 'product_name' => 'Scrunchies', 'variant_label' => '', 'quantity' => 2, 'missing_quantity' => 1],
            ['product_variant_id' => $voucher->id, 'product_name' => 'Voucher kwotowy', 'variant_label' => '250 zł', 'quantity' => 1],
            ['product_variant_id' => null, 'product_name' => 'Pakowanie na prezent', 'variant_label' => '', 'quantity' => 1, 'is_made_to_order' => true],
            ['product_variant_id' => null, 'product_name' => 'Kubek z napisem', 'variant_label' => 'Duży', 'quantity' => 1, 'is_made_to_order' => true, 'custom_text' => "JESZCZE\nNIE TERAZ"],
        ] as $item) {
            $order->items()->create(['unit_price_gross' => 10000, ...$item]);
        }

        return $order;
    }
}
