<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Models\Setting;
use App\Modules\Workshops\Models\WorkshopDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * The search at the top of the panel (MA-308): every module's results, the groups and their order, words without
 * Polish letters, and only the screens an account may open.
 */
class PanelSearchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    private function search(string $term, ?User $user = null): TestResponse
    {
        return $this->actingAs($user ?? User::factory()->create())->getJson('/panel/szukaj?q='.urlencode($term))->assertOk();
    }

    /** @return array<string, list<string>> group => titles */
    private function titles(TestResponse $response): array
    {
        return collect($response->json('groups'))->mapWithKeys(fn (array $group) => [$group['label'] => array_column($group['results'], 'title')])->all();
    }

    private function order(array $attributes = []): Order
    {
        $order = Order::create([
            'status' => OrderStatus::InProgress,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600 100 200',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M',
            'shipping_gross' => 1600,
            'total_gross' => 25500,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
            ...$attributes,
        ]);

        return tap($order)->update(['number' => 'MA-2026-'.(1000 + $order->id)]);
    }

    private function product(string $name, bool $published = true, int $stock = 2): Product
    {
        $category = Category::query()->firstOrCreate(['slug' => 'wazony-i-patery'], ['name' => 'Wazony i patery', 'group' => CategoryGroup::Ceramics]);
        $product = Product::factory()->create(['name' => $name, 'slug' => Str::slug($name), 'category_id' => $category->id, 'is_published' => $published]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Jeden rozmiar', 'price_gross' => 23900, 'stock' => $stock]);

        return $product;
    }

    public function test_products_are_found_without_polish_letters_and_open_their_card(): void
    {
        $bowl = $this->product('Miska żółta');
        $this->product('Wazon niski', published: false);
        $this->product('Patera', stock: 0);

        $response = $this->search('zolta');
        $this->assertSame(['Produkty' => ['Miska żółta']], $this->titles($response));
        $response->assertJsonPath('groups.0.results.0.url', route('admin.products.edit', $bowl))
            ->assertJsonPath('groups.0.results.0.detail', 'Wazony i patery · 239 zł · 2 szt.')
            ->assertJsonPath('groups.0.results.0.meta', null);

        $this->search('wazon niski')->assertJsonPath('groups.0.results.0.meta', 'Ukryty');
        $this->search('patera')->assertJsonPath('groups.0.results.0.meta', 'Wyprzedany');
    }

    public function test_an_order_is_found_by_its_number_name_or_e_mail_also_with_l_for_ł(): void
    {
        $order = $this->order(['name' => 'Łucja Żółkiewska', 'email' => 'lucja@example.com']);
        $this->order(['name' => 'Ola Lis', 'email' => 'ola@example.com']);

        $byNumber = $this->search((string) (1000 + $order->id));
        $byNumber->assertJsonPath('groups.0.label', 'Zamówienia')
            ->assertJsonPath('groups.0.results.0.title', $order->number)
            ->assertJsonPath('groups.0.results.0.url', route('admin.orders.show', $order))
            ->assertJsonPath('groups.0.results.0.meta', '255 zł');
        $this->assertStringStartsWith('Łucja Żółkiewska · W realizacji · ', $byNumber->json('groups.0.results.0.detail'));

        $this->assertSame([$order->number], $this->titles($this->search('lucja zolkiewska'))['Zamówienia']);
        $this->assertSame([$order->number], $this->titles($this->search('żółkiewska łucja'))['Zamówienia']);
        $this->assertSame([$order->number], $this->titles($this->search('LUCJA@example'))['Zamówienia']);
    }

    public function test_a_customer_is_found_by_e_mail_and_opens_her_card(): void
    {
        $this->order(['name' => 'Łucja Żółkiewska', 'email' => 'lucja@example.com']);

        $response = $this->search('lucja@');
        $customer = collect($response->json('groups'))->firstWhere('label', 'Klienci')['results'][0];

        $this->assertSame('Łucja Żółkiewska', $customer['title']);
        $this->assertSame('lucja@example.com · 1 zamówienie', $customer['detail']);
        $this->get($customer['url'])->assertOk()->assertSee('lucja@example.com');
    }

    public function test_a_setting_is_found_by_its_name_and_opens_with_the_cursor_in_its_field(): void
    {
        Setting::query()->updateOrCreate(['key' => 'shipping_methods'], ['value' => [
            ['code' => 'parcel_locker', 'label' => 'Paczkomat InPost', 'price_gross' => 1600, 'price_eur' => 590],
        ]]);

        $response = $this->search('prog');
        $this->assertSame(['Próg darmowej wysyłki'], $this->titles($response)['Ustawienia']);
        $response->assertJsonPath('groups.0.results.0.url', route('admin.settings.edit').'#dostawa-free-shipping-threshold')
            ->assertJsonPath('groups.0.results.0.detail', 'Ustawienia → Dostawa i opłaty');

        // The field the result points at is on the screen, and so are the deliveries from the panel.
        $this->get(route('admin.settings.edit'))->assertSee('id="dostawa-free-shipping-threshold"', false)->assertSee('id="dostawa-shipping-parcel-locker"', false);
        $this->assertContains('Paczkomat InPost — koszt dostawy', $this->titles($this->search('paczkomat'))['Ustawienia']);
        $this->assertContains('Twój Paczkomat — kod dla przesyłek od klientów', $this->titles($this->search('paczkomat'))['Ustawienia']);

        $markup = collect($this->search('narzut euro')->json('groups.0.results'))->firstWhere('title', 'Narzut na ceny w euro');
        $this->assertSame(route('admin.products.settings').'#euro-markup', $markup['url']);
        $this->get(route('admin.products.settings'))->assertSee('id="euro-markup"', false);
    }

    public function test_settings_fields_in_the_index_exist_on_the_settings_screen(): void
    {
        $page = $this->actingAs(User::factory()->create())->get(route('admin.settings.edit'))->assertOk()->getContent();

        // Every field of the index, tab by tab; „Ustawienia produktów” has its own screen, checked above.
        foreach (['dostawa', 'pracownia', 'firma', 'karta produktu', 'statystyki', 'materialy'] as $tab) {
            foreach (collect($this->search($tab)->json('groups'))->firstWhere('label', 'Ustawienia')['results'] ?? [] as $result) {
                if (str_starts_with($result['url'], route('admin.settings.edit').'#')) {
                    $this->assertStringContainsString('id="'.Str::after($result['url'], '#').'"', $page, $result['title']);
                }
            }
        }
    }

    public function test_coming_workshop_dates_are_found_by_name_and_day(): void
    {
        Setting::query()->updateOrCreate(['key' => 'workshop_types'], ['value' => [
            ['code' => 'wheel', 'name' => 'Koło garncarskie', 'price_gross' => 30000],
        ]]);
        $date = WorkshopDate::factory()->create(['workshop_code' => 'wheel', 'starts_at' => now()->addDays(10)->setTime(11, 0), 'ends_at' => now()->addDays(10)->setTime(13, 30), 'seats' => 6]);
        WorkshopDate::factory()->create(['workshop_code' => 'wheel', 'starts_at' => now()->subDays(3), 'seats' => 6]);

        $response = $this->search('kolo garncarskie');
        $this->assertSame(['Koło garncarskie · '.$date->dayLabel()], $this->titles($response)['Warsztaty']);
        $result = collect($response->json('groups'))->firstWhere('label', 'Warsztaty')['results'][0];
        $this->assertSame(route('admin.workshops.dates.bookings', $date), $result['url']);
        $this->assertSame(['11:00 – 13:30', '6 wolnych miejsc'], [$result['detail'], $result['meta']]);

        $this->assertSame(['Koło garncarskie · '.$date->dayLabel()], $this->titles($this->search($date->starts_at->format('j.m')))['Warsztaty']);
    }

    public function test_screens_and_quick_actions_are_found_and_groups_keep_their_order(): void
    {
        $this->product('Kubek wiśniowy');
        $titles = $this->titles($this->search('zwrot'));

        $this->assertContains('Odstąpienia', $titles['Ekrany']);
        $this->assertContains('Adres do zwrotów', $titles['Ustawienia']);
        $this->assertSame(['Ustawienia', 'Ekrany'], array_keys($titles));

        $this->assertSame(['Dodaj produkt'], $this->titles($this->search('dodaj produkt'))['Szybko']);
        $this->search('dodaj termin')->assertJsonFragment(['title' => 'Dodaj termin warsztatu', 'url' => route('admin.workshops.edit').'#termin-workshop']);

        // At most five in a group.
        foreach (range(1, 7) as $number) {
            $this->product('Kubek '.$number);
        }
        $this->assertCount(5, $this->titles($this->search('kubek'))['Produkty']);
    }

    public function test_a_helper_finds_only_orders_and_their_screens(): void
    {
        $order = $this->order(['name' => 'Anna Paczkowska']);
        $this->product('Paczka prezentowa');
        $helper = User::factory()->helper()->create();

        // The product, the settings and the people stay the owner's; her quick actions are about the orders.
        $titles = $this->titles($this->search('paczk', $helper));
        $this->assertSame([$order->number], $titles['Zamówienia']);
        $this->assertSame(['Zamówienia', 'Szybko'], array_keys($titles));
        $this->assertSame([], $this->search('prog', $helper)->json('groups'));
        $this->assertSame(['Zamówienia'], array_keys($this->titles($this->search('ania@example', $helper))));
        $this->assertSame(['Zamówienia'], $this->titles($this->search('zamowienia', $helper))['Ekrany']);

        // The window offers her only the orders.
        $this->get('/panel')->assertOk()->assertSee('Szukaj zamówienia')->assertDontSee('Dodaj produkt');
    }

    public function test_short_odd_or_missing_words_find_nothing_and_a_guest_gets_nothing(): void
    {
        $this->order();

        $this->assertSame([], $this->search('a')->json('groups'));
        $this->assertSame([], $this->search('   ')->json('groups'));
        $this->assertSame([], $this->search('%%')->json('groups'));
        $this->assertSame([], $this->search('__')->json('groups'));

        $this->post('/panel/wyloguj');
        $this->getJson('/panel/szukaj?q=anna')->assertUnauthorized();
    }

    public function test_the_layout_offers_the_search_on_every_screen(): void
    {
        $this->actingAs(User::factory()->create())->get('/panel/produkty')
            ->assertOk()
            ->assertSee('panelSearch(', false)
            ->assertSee('role="combobox"', false)
            ->assertSee('aria-label="Szukaj w panelu"', false)
            ->assertSee('Dodaj termin warsztatu')
            // Signing out clears „Ostatnio otwierane” on the device.
            ->assertSee('x-on:submit="forget()"', false)
            ->assertSee('panel'.str_repeat('\\', 3).'/szukaj', false);
    }
}
