<?php

namespace Tests\Feature\Checkout;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * When the confirmation empties the cart (MA-219, QA CHK-02 and CHK-06): once the order is paid or placed for a bank
 * transfer, and only once per order. With the stand-in gateway a BLIK code pays at once.
 */
class ConfirmationCartTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
        ]]);
    }

    public function test_a_paid_order_empties_the_cart(): void
    {
        $this->addToCart($this->variant('Wazony'));
        $this->post('/zamowienie', $this->form())->assertRedirect(route('checkout.confirmation'));

        $this->get('/zamowienie/potwierdzenie')->assertOk()->assertSee('Zapłacone');
        $this->get('/zamowienie')->assertOk()->assertDontSee('Wazony');
    }

    public function test_an_order_by_bank_transfer_empties_the_cart(): void
    {
        $this->addToCart($this->variant('Wazony'));
        $this->post('/zamowienie', $this->form(['payment_method' => 'bank_transfer', 'blik_code' => '']));

        $this->get('/zamowienie/potwierdzenie')->assertOk()->assertSee('Czekam na przelew')->assertDontSee('Dokończ płatność');
        $this->get('/zamowienie')->assertOk()->assertDontSee('Wazony');
    }

    public function test_a_second_look_at_an_old_confirmation_keeps_the_new_cart(): void
    {
        $this->addToCart($this->variant('Wazony'));
        $this->post('/zamowienie', $this->form());
        $this->get('/zamowienie/potwierdzenie')->assertOk();

        $this->addToCart($this->variant('Miski'));
        $this->get('/zamowienie/potwierdzenie')->assertOk();

        $this->get('/zamowienie')->assertOk()->assertSee('Miski');
    }

    private function variant(string $name): ProductVariant
    {
        $product = Product::factory()->create([
            'name' => $name,
            'slug' => strtolower($name),
            'category_id' => Category::factory()->create()->id,
            'stamp_enabled' => false,
        ]);

        return ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => 3]);
    }

    private function addToCart(ProductVariant $variant): void
    {
        $this->postJson('/koszyk', ['variant_id' => $variant->id]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600 100 200',
            'shipping_method' => 'parcel_locker',
            'payment_method' => 'blik',
            'blik_code' => '123456',
            'accept_terms' => '1',
            'expected_total' => 25500,
            ...$overrides,
        ];
    }
}
