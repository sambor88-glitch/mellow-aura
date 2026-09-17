<?php

namespace Tests\Feature\Gifts;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Gifts\Mail\VouchersIssued;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Support\VoucherPdf;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class VoucherTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'price_gross' => 1600],
        ]]);
        Setting::create(['key' => 'voucher_recipient_name_max_chars', 'value' => 40]);
        Setting::create(['key' => 'voucher_dedication_max_chars', 'value' => 180]);
        Setting::create(['key' => 'voucher_validity_months', 'value' => 12]);
    }

    public function test_the_voucher_page_asks_for_a_name_and_dedication_and_shows_the_printed_card(): void
    {
        $this->voucherVariant();

        $this->get('/produkt/voucher-na-warsztat')
            ->assertOk()
            ->assertSee('name="type" value="voucher"', false)
            ->assertSeeInOrder(['Dla kogo ten voucher?', 'Imię na voucherze', 'Od kogo', 'name="sender_name"', 'Dedykacja', 'Tak będzie wyglądał', 'Voucher na warsztat', 'Ważny do 20 listopada 2027'], false);

        $vase = ProductVariant::factory()->for(Product::factory()->state(['slug' => 'wazony']))->create(['stock' => 3]);

        $this->get('/produkt/wazony')->assertOk()->assertDontSee('Dla kogo ten voucher?');
        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $vase->id])->assertNotFound();
    }

    public function test_each_name_and_dedication_is_its_own_line_within_the_limits(): void
    {
        $voucher = $this->voucherVariant();

        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $voucher->id, 'recipient_name' => ' Ania ', 'dedication' => "Sto lat!\r\nKasia"])
            ->assertOk()
            ->assertJson(['count' => 1, 'notice' => 'Voucher na warsztat — dodane do koszyka']);
        $content = $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $voucher->id, 'recipient_name' => 'Ola'])
            ->assertJson(['count' => 2])
            ->json('content');

        $this->assertStringContainsString("Dla dwóch osób · dla: Ania · dedykacja: „Sto lat!\nKasia”", $content);
        $this->assertStringContainsString('Dla dwóch osób · dla: Ola', $content);

        // A line break sent as \r\n counts as one character, like on the page.
        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $voucher->id, 'dedication' => str_repeat('a', 89)."\r\n".str_repeat('b', 90)])->assertOk();
        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $voucher->id, 'dedication' => str_repeat('a', 181)])
            ->assertUnprocessable()
            ->assertJson(['message' => 'Dedykację zmieszczę do 180 znaków']);
        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $voucher->id, 'recipient_name' => str_repeat('a', 41)])
            ->assertUnprocessable()
            ->assertJson(['message' => 'Imię na voucherze zmieszczę do 40 znaków']);
        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $voucher->id, 'sender_name' => str_repeat('a', 41)])
            ->assertUnprocessable()
            ->assertJson(['message' => 'Podpis „od kogo” zmieszczę do 40 znaków']);

        // The same name from someone else is another voucher.
        $content = $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $voucher->id, 'recipient_name' => 'Ola', 'sender_name' => '  Kasia   i Tomek '])
            ->assertJson(['count' => 4])
            ->json('content');
        $this->assertStringContainsString('Dla dwóch osób · dla: Ola · od: Kasia i Tomek', $content);
    }

    public function test_paying_issues_one_voucher_per_piece_and_mails_them_without_prices(): void
    {
        Mail::fake();
        $voucher = $this->voucherVariant();
        $vase = ProductVariant::factory()->for(Product::factory()->state(['name' => 'Wazony']))->create(['price_gross' => 23900, 'stock' => 3]);
        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $voucher->id, 'quantity' => 2, 'recipient_name' => 'Ania', 'sender_name' => 'Kasia', 'dedication' => 'Sto lat!']);
        $this->postJson('/koszyk', ['variant_id' => $vase->id]);

        $this->post('/zamowienie', $this->form(['expected_total' => 109500]))->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::sole();
        $vouchers = Voucher::query()->orderBy('id')->get();
        $this->assertCount(2, $vouchers);
        $this->assertNotSame($vouchers[0]->code, $vouchers[1]->code);
        $this->assertSame(['Ania', 'Ania'], $vouchers->pluck('recipient_name')->all());
        $this->assertSame(['Kasia', 'Kasia'], $vouchers->pluck('sender_name')->all());
        $this->assertSame('Kasia', $order->items()->where('recipient_name', 'Ania')->value('sender_name'));
        $this->assertSame('Sto lat!', $vouchers[0]->dedication);
        $this->assertSame('2027-11-20', $vouchers[0]->valid_until->toDateString());
        $this->assertSame($order->items()->where('recipient_name', 'Ania')->value('id'), $vouchers[0]->order_item_id);

        app(MarkOrderPaid::class)($order, 'test-repeated-confirmation');
        $this->assertSame(2, Voucher::count());

        Mail::assertQueued(VouchersIssued::class, fn (VouchersIssued $mail) => $mail->hasTo('ania@example.com') && $mail->vouchers->count() === 2);

        $mail = new VouchersIssued($order, $vouchers->load('orderItem'));
        $mail->assertHasSubject('Vouchery z MellowAury');
        $mail->assertSeeInOrderInHtml(['Twoje vouchery', 'Voucher na warsztat', 'dla: Ania', 'od: Kasia', 'ważny do 20 listopada 2027', $vouchers[0]->code]);
        $mail->assertDontSeeInHtml('420,00 zł');
        $mail->assertSeeInText($vouchers[1]->code.': Voucher na warsztat, Dla dwóch osób, dla: Ania, od: Kasia, ważny do 20 listopada 2027');
        $this->assertCount(2, $mail->attachments());

        // The order confirmation names the codes too, next to the order.
        $confirmation = new OrderConfirmed($order->load('items'));
        $confirmation->assertSeeInOrderInHtml(['Vouchery', $vouchers[0]->code, 'dla: Ania', 'od: Kasia', 'ważny do 20 listopada 2027', $vouchers[1]->code, 'PDF-y voucherów wysłałam osobnym mailem']);
        $confirmation->assertSeeInText($vouchers[0]->code.' · dla: Ania · od: Kasia · ważny do 20 listopada 2027');
    }

    public function test_a_voucher_sent_as_a_pdf_needs_no_delivery_but_one_sent_by_post_does(): void
    {
        Mail::fake();
        Setting::create(['key' => 'free_shipping_threshold', 'value' => 50000]);
        Setting::create(['key' => 'dispatch_days_min', 'value' => 3]);
        Setting::create(['key' => 'dispatch_days_max', 'value' => 5]);
        $pdf = $this->voucherVariant();

        $drawer = $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $pdf->id, 'recipient_name' => 'Ania'])->json('content');
        $this->assertStringContainsString('mailem, gratis', $drawer);
        $this->assertStringNotContainsString('Do darmowej wysyłki', $drawer);

        $this->get('/zamowienie')
            ->assertOk()
            ->assertDontSee('name="shipping_method"', false)
            ->assertDontSee('name="street"', false)
            ->assertSeeInOrder(['Dostawa', 'Mailem, w PDF', 'gratis', 'Razem', '420,00 zł'])
            ->assertDontSee('Ceramikę zawijam');

        // The parcel locker sent with the form means nothing for a PDF and costs nothing.
        $this->post('/zamowienie', $this->form(['expected_total' => 42000]))->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::sole();
        $this->assertSame([ShippingMethods::EMAIL, 0, 42000], [$order->shipping_method, $order->shipping_gross, $order->total_gross]);

        $this->get('/zamowienie/potwierdzenie')
            ->assertSeeInOrder(['Dziękuję.', $order->number, 'Dostawa', 'Mailem, w PDF'])
            ->assertDontSee('Pakuję')
            ->assertDontSee('3–5 dni roboczych');

        $confirmation = new OrderConfirmed($order->load('items'));
        $confirmation->assertSeeInOrderInHtml(['Dostawa', 'Mailem, w PDF', 'Na adres ania@example.com']);
        $confirmation->assertDontSeeInHtml('Zanim paczka wyjdzie');

        // A voucher printed and posted travels like a parcel, so the order asks how to send it.
        $posted = ProductVariant::factory()->create(['product_id' => $pdf->product_id, 'label' => 'Wysyłka pocztą', 'price_gross' => 42000, 'stock' => null, 'sent_by_post' => true]);
        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $posted->id, 'recipient_name' => 'Ola']);

        $this->get('/zamowienie')->assertSee('name="shipping_method"', false);
        $this->from('/zamowienie')
            ->post('/zamowienie', $this->form(['shipping_method' => '', 'expected_total' => 43600]))
            ->assertSessionHasErrors(['shipping_method' => 'Wybierz, jak mam wysłać paczkę']);
        $this->post('/zamowienie', $this->form(['expected_total' => 43600]))->assertRedirect('/zamowienie/potwierdzenie');

        $byPost = Order::query()->latest('id')->first();
        $this->assertSame(['parcel_locker', 1600, 43600], [$byPost->shipping_method, $byPost->shipping_gross, $byPost->total_gross]);
    }

    public function test_the_confirmation_page_links_to_each_pdf_with_a_signed_address(): void
    {
        Mail::fake();
        $voucher = $this->voucherVariant();
        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $voucher->id, 'recipient_name' => 'Ania']);
        $this->post('/zamowienie', $this->form(['expected_total' => 42000]));
        $code = Voucher::sole()->code;

        $page = $this->get('/zamowienie/potwierdzenie')
            ->assertOk()
            ->assertSee('Voucher wysłałam Ci osobnym mailem, bez cen')
            ->assertSee('Pobierz voucher '.$code.' dla: Ania');

        preg_match('#href="([^"]*/voucher/'.$code.'[^"]*)"#', $page->getContent(), $link);
        $this->get(html_entity_decode($link[1]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename="voucher-'.$code.'.pdf"')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');

        $this->get('/voucher/'.$code)->assertForbidden();
        $this->get(URL::temporarySignedRoute('vouchers.pdf', now()->subMinute(), ['voucher' => $code]))->assertForbidden();
    }

    public function test_kasia_sees_the_codes_on_the_order_and_downloads_the_pdf(): void
    {
        Mail::fake();
        $voucher = $this->voucherVariant();
        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $voucher->id, 'recipient_name' => 'Ania', 'dedication' => 'Sto lat!']);
        $this->post('/zamowienie', $this->form(['expected_total' => 42000]));
        $issued = Voucher::sole();

        $this->get('/panel/vouchery/'.$issued->id)->assertRedirect('/panel/logowanie');

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.Order::sole()->number)
            ->assertOk()
            ->assertSeeInOrder(['Vouchery', $issued->code, 'Voucher na warsztat · dla: Ania · ważny do 20 listopada 2027', '„Sto lat!”', 'Pobierz PDF']);

        $this->get('/panel/vouchery/'.$issued->id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_printed_voucher_names_both_people_describes_the_workshop_and_gives_the_whatsapp_number(): void
    {
        Mail::fake();
        Setting::create(['key' => 'contact_phone', 'value' => '+48 600 100 200']);
        Setting::create(['key' => 'text_voucher_how_to_use', 'value' => 'napisz do mnie i podaj numer vouchera.']);
        Setting::create(['key' => 'voucher_workshop_notes', 'value' => ['voucher-na-warsztat' => [
            'expect' => 'Trzy godziny przy jednym stole.',
            'takeaway' => 'Dwa kubki po dwóch wypałach.',
        ]]]);
        $voucher = $this->voucherVariant();
        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $voucher->id, 'recipient_name' => 'Nia', 'sender_name' => 'Kasia i Tomek']);
        $this->post('/zamowienie', $this->form(['expected_total' => 42000]));

        $html = app(VoucherPdf::class)->html(Voucher::sole());

        $this->assertMatchesRegularExpression('#<div class="label">Dla</div>\s*<div class="name">Nia</div>#', $html);
        $this->assertMatchesRegularExpression('#<div class="label">Od</div>\s*<div class="name">Kasia i Tomek</div>#', $html);
        $this->assertStringNotContainsString('Szczegóły', $html);
        $this->assertStringNotContainsString('Dla dwóch osób', $html);
        $this->assertMatchesRegularExpression('#Czego się spodziewać</div>\s*<div>Trzy godziny przy jednym stole.</div>#', $html);
        $this->assertMatchesRegularExpression('#Z czym wyjdziecie</div>\s*<div>Dwa kubki po dwóch wypałach.</div>#', $html);
        $this->assertStringNotContainsString('Co będziecie robili', $html);
        $this->assertMatchesRegularExpression('#Jak go wykorzystać:</strong> napisz do mnie i podaj numer vouchera.\s*<strong>WhatsApp: \+48 600 100 200</strong>#', $html);
        $this->assertStringContainsString('www.mellow-aura.com', $html);
        $this->assertStringNotContainsString('localhost', $html);

        // Without a name the voucher keeps a line to write it by hand.
        $unnamed = Voucher::factory()->create(['recipient_name' => null, 'sender_name' => null]);
        $this->assertMatchesRegularExpression('#<div class="label">Od</div>\s*<div class="blank"></div>#', app(VoucherPdf::class)->html($unnamed));
    }

    public function test_the_printed_voucher_says_the_amount_or_the_number_of_people_but_not_how_it_is_delivered(): void
    {
        $workshops = Category::factory()->create(['group' => CategoryGroup::Workshops]);
        $amount = Product::factory()->create(['name' => 'Voucher kwotowy', 'category_id' => $workshops->id]);
        ProductVariant::factory()->create(['product_id' => $amount->id, 'label' => '150 zł', 'price_gross' => 15000, 'stock' => null]);
        $twoFifty = ProductVariant::factory()->create(['product_id' => $amount->id, 'label' => '250 zł', 'price_gross' => 25000, 'stock' => null]);
        $couple = Product::factory()->create(['name' => 'Voucher — warsztat dla pary', 'category_id' => $workshops->id]);
        $printed = ProductVariant::factory()->create(['product_id' => $couple->id, 'label' => 'PDF do wydruku', 'price_gross' => 39000, 'stock' => null]);
        ProductVariant::factory()->create(['product_id' => $couple->id, 'label' => 'Wysyłka pocztą', 'price_gross' => 39000, 'stock' => null]);

        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $twoFifty->id, 'recipient_name' => 'Nia']);
        $this->postJson('/koszyk', ['type' => 'voucher', 'variant_id' => $printed->id, 'recipient_name' => 'Ola']);
        Mail::fake();
        $this->post('/zamowienie', $this->form(['expected_total' => 64000]));

        [$first, $second] = Voucher::query()->orderBy('id')->get()->all();
        $pdf = app(VoucherPdf::class);

        $this->assertStringContainsString('<div class="eyebrow">Voucher kwotowy · 250 zł</div>', $pdf->html($first));
        $this->assertStringContainsString('<div class="eyebrow">Voucher — warsztat dla pary</div>', $pdf->html($second));
        $this->assertStringNotContainsString('PDF do wydruku', $pdf->html($second));
    }

    private function voucherVariant(): ProductVariant
    {
        $product = Product::factory()->create([
            'name' => 'Voucher na warsztat',
            'slug' => 'voucher-na-warsztat',
            'category_id' => Category::factory()->create(['group' => CategoryGroup::Workshops])->id,
        ]);

        return ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Dla dwóch osób', 'price_gross' => 42000, 'stock' => null]);
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
            ...$overrides,
        ];
    }
}
