<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\AnonymizeCustomer;
use App\Modules\Checkout\Mail\NewOrderReceived;
use App\Modules\Checkout\Mail\OrderAwaitingPayment;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\GiftCardPdf;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * A few words from the buyer to the person who gets the gift (MA-342): typed in the checkout, kept on the order,
 * shown to Kasia in the panel and her mail, printed on an A6 card for the parcel — and never in the buyer's own mails.
 */
class GiftMessageTest extends TestCase
{
    use RefreshDatabase;

    /** The folded gift field, opened. */
    private const GIFT_OPEN = '/<details\s+open\s+class="group mt-1 /';

    private const MESSAGE = "Wszystkiego dobrego, Olu!\nPij z niego kawę powoli.\n— Marta";

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        Setting::create(['key' => 'contact_email', 'value' => 'kasia@example.com']);
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600, 'price_eur' => 450],
        ]]);
    }

    public function test_the_checkout_offers_a_folded_optional_field_for_the_gift_message(): void
    {
        $this->basket();

        $page = $this->get('/zamowienie')
            ->assertOk()
            ->assertSeeInOrder(['Dopisz kilka słów do obdarowanej', 'Liścik do prezentu', 'name="gift_message"', 'maxlength="300"'], false)
            ->assertSee('Wydrukuję go na kartce i włożę do paczki.')
            ->assertSee('0 z 300 znaków');

        $this->assertDoesNotMatchRegularExpression(self::GIFT_OPEN, $page->getContent());
    }

    public function test_the_message_is_saved_tidied(): void
    {
        $this->basket();

        $this->post('/zamowienie', $this->form(['gift_message' => "  Wszystkiego dobrego, Olu!\r\nPij z niego kawę powoli.\r\n\r\n\r\n\r\n— Marta  "]))
            ->assertRedirect('/zamowienie/potwierdzenie');

        $this->assertSame("Wszystkiego dobrego, Olu!\nPij z niego kawę powoli.\n\n— Marta", Order::sole()->gift_message);
    }

    public function test_a_message_of_blanks_alone_keeps_none(): void
    {
        $this->basket();

        $this->post('/zamowienie', $this->form(['gift_message' => "  \r\n "]))->assertRedirect('/zamowienie/potwierdzenie');

        $this->assertNull(Order::sole()->gift_message);
    }

    public function test_the_server_keeps_the_message_to_what_fits_on_the_card(): void
    {
        $this->basket();

        // 300 characters, Polish letters and line breaks included, fit; one more does not.
        $fits = str_repeat('ż', 290)."\r\n".str_repeat('a', 9);
        $page = $this->from('/zamowienie')
            ->followingRedirects()
            ->post('/zamowienie', $this->form(['gift_message' => $fits.'a']))
            ->assertSee('Na kartce zmieszczę do 300 znaków — skróć liścik')
            ->assertSee('aria-invalid="true"', false);
        // The field opens by itself with what was typed, so nothing has to be written again.
        $this->assertMatchesRegularExpression(self::GIFT_OPEN, $page->getContent());
        $this->assertSame(0, Order::count());

        $this->post('/zamowienie', $this->form(['gift_message' => $fits]))->assertRedirect('/zamowienie/potwierdzenie');
        $this->assertSame(300, mb_strlen(Order::sole()->gift_message));
    }

    public function test_kasia_sees_the_message_escaped_in_the_panel_and_prints_it_on_a_card(): void
    {
        $this->basket();
        $this->post('/zamowienie', $this->form(['gift_message' => "<script>alert('x')</script>\nDla Oli"]))->assertRedirect('/zamowienie/potwierdzenie');
        $order = Order::sole();

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertOk()
            ->assertSeeInOrder(['Liścik do prezentu', 'Drukuj liścik', '&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;', 'Dla Oli'], false)
            ->assertSee('href="'.route('admin.orders.gift-card', $order).'"', false)
            ->assertDontSee("<script>alert('x')</script>", false);

        $this->get(route('admin.orders.gift-card', $order))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'inline; filename="liscik-'.$order->number.'.pdf"');

        // The card holds the words and the brand, escaped, with the line breaks kept — no price, name or number.
        $html = app(GiftCardPdf::class)->html($order);
        $this->assertStringContainsString('kilka słów dla Ciebie', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(&#039;x&#039;)&lt;/script&gt;<br>'."\n".'Dla Oli', $html);
        $this->assertStringNotContainsString("<script>alert('x')</script>", $html);
        $this->assertStringNotContainsString($order->number, $html);
        $this->assertStringNotContainsString('Anna Nowak', $html);
        $this->assertStringNotContainsString('zł', $html);
    }

    public function test_an_order_without_a_message_has_no_card_to_print(): void
    {
        $this->basket();
        $this->post('/zamowienie', $this->form())->assertRedirect('/zamowienie/potwierdzenie');
        $order = Order::sole();

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertDontSee('Drukuj liścik');
        $this->get(route('admin.orders.gift-card', $order))->assertNotFound();
    }

    public function test_the_message_goes_to_kasia_but_never_into_the_buyers_mails(): void
    {
        $this->basket();
        $this->post('/zamowienie', $this->form(['gift_message' => self::MESSAGE.' <b>!</b>']))->assertRedirect('/zamowienie/potwierdzenie');
        $order = Order::sole();

        $kasia = new NewOrderReceived($order);
        $kasia->assertSeeInOrderInHtml(['Liścik do prezentu', 'Wszystkiego dobrego, Olu!<br />', 'Pij z niego kawę powoli.', '— Marta &lt;b&gt;!&lt;/b&gt;'], false);
        $kasia->assertSeeInText("LIŚCIK DO PREZENTU (wydrukujesz go z karty zamówienia w panelu)\n".self::MESSAGE.' <b>!</b>');

        foreach ([new OrderConfirmed($order), new OrderAwaitingPayment($order)] as $mail) {
            $mail->assertDontSeeInHtml('Olu');
            $mail->assertDontSeeInText('Olu');
            $mail->assertDontSeeInHtml('Liścik');
        }
    }

    public function test_forgetting_the_customer_wipes_the_message(): void
    {
        $this->basket();
        $this->post('/zamowienie', $this->form(['gift_message' => self::MESSAGE]))->assertRedirect('/zamowienie/potwierdzenie');

        app(AnonymizeCustomer::class)('ania@example.com');

        $this->assertNull(Order::sole()->gift_message);
    }

    public function test_the_english_checkout_takes_the_message_and_prints_the_card_in_english(): void
    {
        $category = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $category->translations()->create(['locale' => 'en', 'name' => 'Ceramics', 'slug' => 'ceramics']);
        $product = Product::factory()->create(['name' => 'Malowany kubek', 'category_id' => $category->id, 'stamp_enabled' => false]);
        $product->translations()->create(['locale' => 'en', 'name' => 'Painted mug', 'slug' => 'painted-mug']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => '', 'price_gross' => 14900, 'stock' => 10]);
        $variant->prices()->create(['currency' => 'EUR', 'amount_minor' => 3900]);
        $this->postJson('/en/basket', ['variant_id' => $variant->id])->assertOk();

        $this->get('/en/checkout')
            ->assertSeeInOrder(['Add a few words for the recipient', 'Gift note', 'name="gift_message"'], false)
            ->assertSee('I’ll print it on a card and tuck it into the parcel.');

        $this->from('/en/checkout')
            ->followingRedirects()
            ->post('/en/checkout', [...$this->form(['payment_method' => 'card', 'expected_total' => 4350]), 'gift_message' => str_repeat('a', 301)])
            ->assertSee('The card fits 300 characters — please make the note shorter');

        $this->post('/en/checkout', [...$this->form(['payment_method' => 'card', 'expected_total' => 4350]), 'gift_message' => 'Happy birthday, Ola!'])
            ->assertRedirect('/en/checkout/confirmation');

        $order = Order::sole();
        $this->assertSame('Happy birthday, Ola!', $order->gift_message);
        $this->assertStringContainsString('a few words for you', app(GiftCardPdf::class)->html($order));
        $this->assertSame('gift-note-'.$order->number.'.pdf', GiftCardPdf::filename($order));
    }

    private function basket(): void
    {
        $product = Product::factory()->create([
            'name' => 'Wazony', 'category_id' => Category::factory()->create()->id, 'is_published' => true, 'stamp_enabled' => false,
        ]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => 3]);

        $this->postJson('/koszyk', ['variant_id' => $variant->id])->assertOk();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [
            'phone' => '+48 600 100 200',
            'email' => 'ania@example.com',
            'name' => 'Anna Nowak',
            'shipping_method' => 'parcel_locker',
            'locker_code' => 'KRA01M',
            'payment_method' => 'blik',
            'blik_code' => '123456',
            'accept_terms' => '1',
            'expected_total' => 25500,
            ...$overrides,
        ];
    }
}
