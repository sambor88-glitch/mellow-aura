<?php

namespace Tests\Feature\Checkout;

use App\Modules\Cart\Lines\ProductLine;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Actions\PlaceOrder;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\NewOrderReceived;
use App\Modules\Checkout\Mail\OrderAwaitingPayment;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Mail\OrderUnavailable;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class OrderEmailsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
            ['code' => 'courier', 'label' => 'Kurier InPost', 'price_gross' => 2200],
            ['code' => 'studio_pickup', 'label' => 'Odbiór w pracowni', 'price_gross' => 0],
        ]]);
    }

    public function test_paying_sends_the_customer_a_confirmation_of_what_was_bought(): void
    {
        Mail::fake();
        Setting::create(['key' => 'contact_email', 'value' => 'kasia@example.com']);
        Setting::create(['key' => 'contact_phone', 'value' => '+48 600 700 800']);
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3);
        $mug = $this->variant('Kubki z cytatem', 'Twój tekst', 7900, stock: null, product: ['stamp_enabled' => true]);
        $this->postJson('/koszyk', ['variant_id' => $vase->id]);
        $this->postJson('/koszyk', ['variant_id' => $mug->id, 'custom_text' => 'jeszcze nie teraz']);

        $this->post('/zamowienie', $this->form(['expected_total' => 33400]))->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::sole();
        Mail::assertSent(OrderConfirmed::class, fn (OrderConfirmed $mail) => $mail->hasTo('ania@example.com', 'Anna Nowak') && $mail->order->is($order));
        // The summary right after ordering, the confirmation once paid and Kasia's notice.
        Mail::assertSent(OrderAwaitingPayment::class, fn (OrderAwaitingPayment $mail) => $mail->hasTo('ania@example.com', 'Anna Nowak') && $mail->order->is($order));
        Mail::assertSentCount(3);

        $mail = new OrderConfirmed($order);
        $mail->assertHasSubject('Zamówienie '.$order->number.' jest opłacone');
        $mail->assertHasReplyTo('kasia@example.com');
        $mail->assertSeeInOrderInHtml(['Dziękuję. Pakuję.', $order->number, 'Wazony', 'Niski 16 cm', '239,00 zł', 'Kubki z cytatem', 'JESZCZE NIE TERAZ', '79,00 zł', '318,00 zł', 'InPost Paczkomat', '16,00 zł', '334,00 zł']);
        $mail->assertSeeInHtml('Paczkomat dobiorę po numerze telefonu 600100200');
        $mail->assertSeeInHtml('Możesz też napisać na WhatsAppie: +48 600 700 800.');
        $mail->assertDontSeeInHtml('Ktoś kupił ostatnią sztukę');
        $mail->assertSeeInText('Napis: „JESZCZE NIE TERAZ”');
        $mail->assertSeeInText('Razem: 334,00 zł');
        $mail->assertSeeInHtml('W załącznikach jest regulamin sklepu (wersja 0.2 z 16 września 2026) i wzór formularza odstąpienia od umowy.');
    }

    public function test_the_confirmation_carries_the_terms_and_the_withdrawal_form_as_pdfs(): void
    {
        Storage::fake('local');
        Mail::fake();
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);
        $this->post('/zamowienie', $this->form())->assertRedirect('/zamowienie/potwierdzenie');

        $attachments = collect((new OrderConfirmed(Order::sole()))->attachments());

        $this->assertSame(['MellowAura-regulamin.pdf', 'MellowAura-formularz-odstapienia.pdf'], $attachments->map(fn (Attachment $attachment) => $attachment->as)->all());
        $attachments->each(function (Attachment $attachment) {
            $this->assertSame('application/pdf', $attachment->mime);
            $this->assertStringStartsWith('%PDF-', $attachment->attachWith(fn () => null, fn (\Closure $data) => $data()));
        });
    }

    public function test_kasia_gets_everything_needed_to_pack_and_can_reply_to_the_customer(): void
    {
        Mail::fake();
        Setting::create(['key' => 'contact_email', 'value' => 'kasia@example.com']);
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);

        $this->post('/zamowienie', $this->form([
            'shipping_method' => 'courier', 'street' => 'Długa 5', 'postal_code' => '30-001', 'city' => 'Kraków',
            'invoice_nip' => '111-111-11-11', 'note' => 'Proszę bez paragonu w środku', 'expected_total' => 26100,
        ]))->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::sole();
        Mail::assertSent(NewOrderReceived::class, fn (NewOrderReceived $mail) => $mail->hasTo('kasia@example.com'));

        $mail = new NewOrderReceived($order);
        $mail->assertHasSubject('Nowe zamówienie '.$order->number.' · 261,00 zł');
        $mail->assertHasReplyTo('ania@example.com', 'Anna Nowak');
        $mail->assertSeeInHtml('Kurier InPost');
        $mail->assertSeeInHtml('Długa 5, 30-001 Kraków');
        $mail->assertSeeInHtml('Anna Nowak · 600100200 · ania@example.com');
        $mail->assertSeeInHtml('Faktura na NIP 1111111111');
        $mail->assertSeeInHtml('Dopisek: Proszę bez paragonu w środku');
        $mail->assertSeeInHtml(route('admin.orders.show', $order));
        $mail->assertSeeInText('Otwórz zamówienie w panelu: '.route('admin.orders.show', $order));
    }

    public function test_without_a_contact_address_only_the_customer_gets_a_mail(): void
    {
        Mail::fake();
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);

        $this->post('/zamowienie', $this->form())->assertRedirect('/zamowienie/potwierdzenie');

        Mail::assertSent(OrderConfirmed::class);
        Mail::assertNotSent(NewOrderReceived::class);

        (new OrderConfirmed(Order::sole()))->assertSeeInHtml('Napisz do mnie przez stronę sklepu i podaj numer');
    }

    public function test_a_rejected_payment_sends_no_mail(): void
    {
        Mail::fake();
        Setting::create(['key' => 'contact_email', 'value' => 'kasia@example.com']);
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);

        $this->from('/zamowienie')->post('/zamowienie', $this->form(['blik_code' => '000000']));

        $this->assertSame(PaymentStatus::Failed, Order::sole()->payment_status);
        Mail::assertNothingSent();
    }

    public function test_a_payment_confirmed_twice_sends_each_mail_once(): void
    {
        Mail::fake();
        Setting::create(['key' => 'contact_email', 'value' => 'kasia@example.com']);
        $order = $this->placeOrder($this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3));

        app(MarkOrderPaid::class)($order, 'test-first');
        app(MarkOrderPaid::class)($order, 'test-repeated');

        Mail::assertSent(OrderConfirmed::class, 1);
        Mail::assertSent(NewOrderReceived::class, 1);
    }

    public function test_the_summary_says_the_contract_comes_with_the_payment_and_gives_the_bank_details_for_a_transfer(): void
    {
        Setting::create(['key' => 'company_name', 'value' => 'MellowAura Katarzyna Samborska']);
        Setting::create(['key' => 'company_bank_account', 'value' => '12 3456 7890 1234 5678 9012 3456']);
        $order = $this->placeOrder($this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3), ['payment_method' => 'bank_transfer']);

        $mail = new OrderAwaitingPayment($order->load('items'));
        $mail->assertHasSubject('Podsumowanie zamówienia '.$order->number);
        $mail->assertSeeInOrderInHtml(['Dziękuję za zamówienie', $order->number, 'czeka na płatność', 'Z tą chwilą zawieramy umowę.', 'Dane do przelewu', 'Kwota: 255,00 zł', 'Tytuł: '.$order->number, 'Rachunek: 12 3456 7890 1234 5678 9012 3456', 'Odbiorca: MellowAura Katarzyna Samborska', 'Wazony', '239,00 zł', 'Płatność: Przelew tradycyjny']);
        $mail->assertSeeInText('Kwota: 255,00 zł');

        $blik = new OrderAwaitingPayment($this->placeOrder($this->variant('Patery', 'Duża', 32900, stock: 1))->load('items'));
        $blik->assertDontSeeInHtml('Dane do przelewu');
        $blik->assertSeeInHtml('Płatność: BLIK');
    }

    public function test_a_piece_someone_else_paid_for_first_comes_back_as_money_in_the_confirmation(): void
    {
        Mail::fake();
        Setting::create(['key' => 'contact_email', 'value' => 'kasia@example.com']);
        $holder = $this->variant('Kadzielnice', 'Złoty kołnierz', 9900, stock: 1);
        $vase = $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3);
        $first = $this->placeOrder($holder);
        $second = app(PlaceOrder::class)(collect([
            'v'.$holder->id => new ProductLine('v'.$holder->id, 1, $holder->load('product')),
            'v'.$vase->id => new ProductLine('v'.$vase->id, 1, $vase->load('product')),
        ]), ['name' => 'Anna Nowak', 'email' => 'ania@example.com', 'phone' => '600100200', 'shipping_method' => 'parcel_locker', 'payment_method' => 'blik'], 1600);

        app(MarkOrderPaid::class)($first, 'test-first');
        app(MarkOrderPaid::class)($second, 'test-second');

        Mail::assertSent(OrderConfirmed::class, fn (OrderConfirmed $mail) => $mail->order->is($second));
        $second->refresh();
        (new OrderConfirmed($second))
            ->assertSeeInHtml('Ktoś kupił ostatnią sztukę chwilę przed zaksięgowaniem Twojej płatności:')
            ->assertSeeInHtml('Kadzielnice (Złoty kołnierz).')
            ->assertSeeInHtml('Za brakujące sztuki zwrócę Ci 99,00 zł — najpóźniej w ciągu 14 dni.')
            ->assertSeeInText('Za brakujące sztuki zwrócę Ci 99,00 zł — najpóźniej w ciągu 14 dni. Jeśli wolisz podobną sztukę na zamówienie, odpisz na tego maila.');
        (new NewOrderReceived($second))
            ->assertSeeInHtml('Brakuje na półce:')
            ->assertSeeInHtml('Kadzielnice (Złoty kołnierz, 1 szt.)')
            ->assertSeeInHtml('Klientka dostała maila, że zwrócisz jej 99,00 zł najpóźniej w ciągu 14 dni.');
        (new OrderConfirmed($first->refresh()))->assertDontSeeInHtml('Ktoś kupił ostatnią sztukę');
    }

    public function test_an_order_with_nothing_left_gets_the_whole_payment_back_instead_of_a_confirmation(): void
    {
        Mail::fake();
        Setting::create(['key' => 'contact_email', 'value' => 'kasia@example.com']);
        $holder = $this->variant('Kadzielnice', 'Złoty kołnierz', 9900, stock: 1);
        $first = $this->placeOrder($holder);
        $second = $this->placeOrder($holder);

        app(MarkOrderPaid::class)($first, 'test-first');
        app(MarkOrderPaid::class)($second, 'test-second');

        Mail::assertSent(OrderUnavailable::class, fn (OrderUnavailable $mail) => $mail->order->is($second) && $mail->hasTo('ania@example.com'));
        Mail::assertNotSent(OrderConfirmed::class, fn (OrderConfirmed $mail) => $mail->order->is($second));

        $second->refresh();
        (new OrderUnavailable($second))
            ->assertHasSubject('Zamówienie '.$second->number.' — zwrócę całą wpłatę')
            ->assertSeeInOrderInHtml(['Przepraszam — tego już nie ma na półce', 'Kadzielnice (Złoty kołnierz)', 'nie wyślę zamówienia '.$second->number, 'Zwrócę Ci całą wpłatę', '115,00 zł', 'najpóźniej w ciągu 14 dni', 'Jeśli wolisz podobną sztukę na zamówienie, odpisz na tego maila.'])
            ->assertSeeInText('Zwrócę Ci całą wpłatę — 115,00 zł — najpóźniej w ciągu 14 dni.');
        (new NewOrderReceived($second))
            ->assertSeeInHtml('Nic z tego zamówienia nie zostało na półce:')
            ->assertSeeInHtml('zwrócisz jej 115,00 zł');
    }

    public function test_a_pickup_gets_no_address_and_the_customers_text_is_escaped(): void
    {
        $mug = $this->variant('Kubki z cytatem', 'Twój tekst', 7900, stock: null, product: ['stamp_enabled' => true]);
        $order = $this->placeOrder($mug, ['shipping_method' => 'studio_pickup'], customText: '<b>ALE JEDNAK</b>');

        $mail = new OrderConfirmed($order->load('items'));
        $mail->assertSeeInHtml('Odbiór w pracowni');
        $mail->assertSeeInHtml('Napiszę, kiedy i gdzie możesz odebrać zamówienie');
        $mail->assertSeeInHtml('&lt;b&gt;ALE JEDNAK&lt;/b&gt;', false);
        $mail->assertDontSeeInHtml('<b>ALE JEDNAK</b>', false);
    }

    public function test_a_mail_that_cannot_be_sent_is_reported_and_the_order_stays_paid(): void
    {
        Exceptions::fake();
        Mail::shouldReceive('to')->andThrow(new RuntimeException('Serwer poczty nie odpowiada'));
        $this->postJson('/koszyk', ['variant_id' => $this->variant('Wazony', 'Niski 16 cm', 23900, stock: 3)->id]);

        $this->post('/zamowienie', $this->form())->assertRedirect('/zamowienie/potwierdzenie');

        $this->assertSame(PaymentStatus::Paid, Order::sole()->payment_status);
        Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Serwer poczty nie odpowiada');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function placeOrder(ProductVariant $variant, array $data = [], ?string $customText = null): Order
    {
        $line = new ProductLine('v'.$variant->id, 1, $variant->load('product'), $customText);

        return app(PlaceOrder::class)(collect([$line->key => $line]), [
            'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'phone' => '600100200',
            'shipping_method' => 'parcel_locker', 'payment_method' => 'blik', ...$data,
        ], 1600);
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
            'payment_method' => 'blik',
            'blik_code' => '123456', 'accept_terms' => '1',
            'expected_total' => 25500,
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function variant(string $name, string $label, int $price, ?int $stock, array $product = []): ProductVariant
    {
        $owner = Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'category_id' => Category::factory()->create()->id,
            'is_published' => true,
            'stamp_enabled' => false,
            ...$product,
        ]);

        return ProductVariant::factory()->create(['product_id' => $owner->id, 'label' => $label, 'price_gross' => $price, 'stock' => $stock]);
    }
}
