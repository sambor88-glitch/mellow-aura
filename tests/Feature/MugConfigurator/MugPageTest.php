<?php

namespace Tests\Feature\MugConfigurator;

use App\Models\User;
use App\Modules\Checkout\Actions\MarkOrderPaid;
use App\Modules\Checkout\Mail\OrderConfirmed;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MugPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_page_shows_the_photo_the_sizes_and_the_glazes_from_the_panel(): void
    {
        $this->get('/kubek-z-napisem')
            ->assertOk()
            ->assertSee('<title>Kubek z napisem na zamówienie — Twój tekst wbity w glinę</title>', false)
            ->assertSee('<link rel="canonical" href="'.route('mug.index').'">', false)
            ->assertSeeInOrder(['Strona główna', 'Kubek z napisem'])
            ->assertSeeInOrder(['na zamówienie', 'gotowe w 3 tygodnie', "Kubek, który mówi to,\nco myślisz", 'Wbijam litery stemplem'])
            ->assertSeeInOrder(['Twój napis', 'name="text"', 'Wycisz stempel', 'Podziel na linie'], false)
            ->assertSeeInOrder(['Rozmiar', 'Mały 200 ml', '69,00 zł', 'value="Średni" x-model="size" checked', 'Średni 300 ml', '79,00 zł', 'Duży 400 ml', '95,00 zł'], false)
            ->assertSeeInOrder(['Kolor wnętrza', 'value="turquoise" x-model="glaze" checked', 'Turkus', 'Kobalt', 'Ceglany', 'Grafit', 'Pudrowy róż'], false)
            ->assertSeeInOrder(['Dodaj do koszyka', '79,00 zł', 'wysyłka gratis od 400,00 zł'])
            ->assertSeeInOrder(['Zamawiasz więcej?', 'Od sześciu kubków robię rabat', 'Czego nie wbiję', 'Mowy nienawiści'])
            ->assertSee('Podgląd jest orientacyjny');
    }

    public function test_without_sizes_there_is_nothing_to_sell(): void
    {
        Setting::query()->where('key', 'mug_sizes')->update(['value' => json_encode([])]);

        $this->get('/kubek-z-napisem')->assertNotFound();
    }

    public function test_the_text_is_tidied_and_checked_on_the_server(): void
    {
        $content = $this->postJson('/koszyk', ['type' => 'mug', 'text' => "  nie   powinnam \r\n\r\n ale jednak ", 'size' => 'Duży', 'glaze' => 'cobalt'])
            ->assertOk()
            ->assertJson(['count' => 1, 'notice' => 'Kubek z Twoim napisem w koszyku'])
            ->json('content');
        $this->assertStringContainsString('Kubek z napisem', $content);
        $this->assertStringContainsString('„NIE POWINNAM / ALE JEDNAK” · Duży 400 ml · wnętrze kobalt', $content);
        $this->assertStringContainsString('95,00 zł', $content);

        // The same mug again makes the line grow; another glaze is another line.
        $this->postJson('/koszyk', ['type' => 'mug', 'text' => 'NIE POWINNAM'."\n".'ALE JEDNAK', 'size' => 'Duży', 'glaze' => 'cobalt'])->assertJson(['count' => 2]);
        $this->postJson('/koszyk', ['type' => 'mug', 'text' => 'nie powinnam', 'size' => 'Duży', 'glaze' => 'graphite'])->assertJson(['count' => 3]);
        $this->assertStringContainsString('&lt;B&gt;KAWA&lt;/B&gt;', $this->postJson('/koszyk', ['type' => 'mug', 'text' => '<b>kawa</b>', 'size' => 'Mały', 'glaze' => 'turquoise'])->json('content'));

        $mistakes = [
            [['text' => ' '], 'Napisz, co mam wbić w glinę'],
            [['text' => "jeden\ndwa\ntrzy\ncztery"], 'Zmieszczę najwyżej 3 linie napisu'],
            [['text' => str_repeat('A', 17)], 'W jednej linii zmieszczę najwyżej 16 znaków — podziel napis na linie'],
            [['text' => 'kawa ☕'], 'Wbiję litery, cyfry i znaki interpunkcyjne — bez emotek'],
            [['size' => 'Olbrzymi'], 'Wybierz rozmiar kubka'],
            [['glaze' => 'neon'], 'Wybierz kolor wnętrza'],
        ];

        foreach ($mistakes as [$change, $message]) {
            $this->postJson('/koszyk', ['type' => 'mug', 'text' => 'kawa', 'size' => 'Mały', 'glaze' => 'turquoise', ...$change])
                ->assertUnprocessable()
                ->assertJson(['message' => $message]);
        }
    }

    public function test_the_order_keeps_the_text_line_by_line_and_the_glaze_by_name(): void
    {
        Mail::fake();
        Setting::query()->where('key', 'contact_email')->update(['value' => json_encode('kasia@example.com')]);
        $this->postJson('/koszyk', ['type' => 'mug', 'text' => "nie powinnam\nale jednak", 'size' => 'Średni', 'glaze' => 'powder_pink', 'quantity' => 2]);

        $this->post('/zamowienie', [
            'phone' => '600 100 200', 'email' => 'ania@example.com', 'name' => 'Anna Nowak',
            'shipping_method' => 'parcel_locker', 'payment_method' => 'blik', 'blik_code' => '123456', 'accept_terms' => '1', 'expected_total' => 2 * 7900 + 1600,
        ])->assertRedirect('/zamowienie/potwierdzenie');

        $order = Order::sole();
        $item = $order->items()->sole();
        $this->assertSame(['Kubek z napisem', 'Średni 300 ml', 2, 7900], [$item->product_name, $item->variant_label, $item->quantity, $item->unit_price_gross]);
        $this->assertSame("NIE POWINNAM\nALE JEDNAK", $item->custom_text);
        $this->assertSame('Pudrowy róż', $item->custom_glaze);
        $this->assertNull($item->product_variant_id);
        $this->assertTrue($item->is_made_to_order);
        $this->assertFalse($order->hasShortage());

        app(MarkOrderPaid::class)($order, 'test-repeated-confirmation');
        $this->assertSame(0, $item->fresh()->missing_quantity);

        $mail = new OrderConfirmed($order);
        $mail->assertSeeInHtml('Napis: „NIE POWINNAM / ALE JEDNAK”');
        $mail->assertSeeInHtml('Kolor wnętrza: Pudrowy róż');
        $mail->assertSeeInText('Napis: „NIE POWINNAM / ALE JEDNAK”');

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia/'.$order->number)
            ->assertOk()
            ->assertSeeInOrder(['Kubek z napisem', 'Średni 300 ml', 'Napis do wbicia:', "„NIE POWINNAM\nALE JEDNAK”", 'Kolor wnętrza:', 'Pudrowy róż']);
    }

    public function test_a_size_removed_in_the_panel_takes_its_mugs_out_of_the_cart(): void
    {
        $this->postJson('/koszyk', ['type' => 'mug', 'text' => 'kawa', 'size' => 'Duży', 'glaze' => 'cobalt'])->assertJson(['count' => 1]);

        Setting::query()->where('key', 'mug_sizes')->update(['value' => json_encode([['label' => 'Mały', 'capacity_ml' => 200, 'price_gross' => 6900]])]);
        app(Settings::class)->refresh();

        $this->assertStringContainsString('Jeszcze pusto', $this->deleteJson('/koszyk/m-000000000000')->assertJson(['count' => 0])->json('content'));
    }
}
