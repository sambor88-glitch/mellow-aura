<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Checkout\Enums\ComplaintDecision;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\ComplaintAnswer;
use App\Modules\Checkout\Models\Order;
use App\Modules\Content\Mail\ContactMessageReceived;
use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

/**
 * The panel menu in four groups, with a number next to what waits, and hidden under „Menu” on a phone (MA-249).
 */
class PanelMenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_owner_sees_four_groups_in_order(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia')
            ->assertOk()
            ->assertSeeInOrder([
                'Mój panel',
                'Sprzedaż', 'Zamówienia', 'Zamówienia indywidualne', 'Klientki', 'Odstąpienia', 'Reklamacje',
                'Oferta', 'Produkty', 'Kategorie', 'Kubek z napisem', 'Prezenty i zestawy', 'Warsztaty', 'Wypały', 'Usługi',
                'Strona', 'Treści', 'Zdjęcia stron', 'Google', 'Maile', 'Certyfikat unikatu',
                'Ustawienia', 'Ustawienia', 'Konta', 'Niewysłane maile',
            ]);
    }

    public function test_the_firing_batches_and_price_list_are_one_section_with_two_tabs(): void
    {
        $owner = User::factory()->create();
        $menu = fn (string $page) => str($this->actingAs($owner)->get($page)->assertOk()->getContent())->between('id="panel-menu"', '</nav>');

        foreach (['/panel/wypaly', '/panel/wypaly/zgloszenia'] as $page) {
            $this->assertStringNotContainsString('Zgłoszenia do wypału', (string) $menu($page));
            $this->assertMatchesRegularExpression('/aria-current="page"[^>]*>Wypały<\/a>/', (string) $menu($page));
        }

        $this->get('/panel/wypaly')->assertSeeInOrder(['Zgłoszenia', 'Cennik i dzień wsadu']);
        $this->get('/panel/wypaly/zgloszenia')->assertSee('Cennik i dzień wsadu');
    }

    public function test_the_four_sections_count_what_waits(): void
    {
        config(['queue.default' => 'database']);
        $this->order(OrderStatus::InProgress);
        $this->order(OrderStatus::InProgress);
        $this->order(OrderStatus::Shipped);
        $this->order(OrderStatus::InProgress, PaymentStatus::Pending);
        $this->idea(CustomOrderStatus::New);
        $this->idea(CustomOrderStatus::Quoting);
        $this->complaint(emailed: false);
        $this->complaint(emailed: true);
        $this->failedMail();

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia')
            ->assertSee(', 2 do wysłania', false)
            ->assertSee(', 1 nowe', false)
            ->assertSee(', 1 odpowiedzi nie wyszły', false)
            ->assertSee(', 1 do ponowienia', false)
            // The phone's „Menu” adds them up.
            ->assertSee(', 5 spraw czeka', false);
    }

    public function test_nothing_waiting_shows_no_number(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia')
            ->assertDontSee('do wysłania</span>', false)
            ->assertDontSee('spraw czeka')
            ->assertDontSee('sprawa czeka');
    }

    public function test_a_helper_sees_only_the_sales_group_with_the_orders(): void
    {
        $this->order(OrderStatus::InProgress);
        $this->idea(CustomOrderStatus::New);

        $response = $this->actingAs(User::factory()->helper()->create())->get('/panel/zamowienia')->assertOk();
        $menu = (string) str($response->getContent())->between('id="panel-menu"', '</nav>');

        $this->assertStringContainsString('Sprzedaż', $menu);
        $this->assertStringContainsString('Zamówienia', $menu);
        $this->assertStringContainsString(', 1 do wysłania', $menu);
        foreach (['Oferta', 'Strona', 'Ustawienia', 'Zamówienia indywidualne', 'Klientki', 'Niewysłane maile', 'nowe'] as $hidden) {
            $this->assertStringNotContainsString($hidden, $menu);
        }
        // Only what the helper can open counts on the phone's „Menu”.
        $response->assertSee(', 1 sprawa czeka', false);
    }

    public function test_on_a_phone_the_menu_hides_under_a_button_that_names_the_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia')
            ->assertSee('aria-controls="panel-menu"', false)
            ->assertSee('aria-expanded="false"', false)
            ->assertSee('x-bind:aria-expanded="menu.toString()"', false)
            ->assertSee('id="panel-menu"', false)
            ->assertSee("x-bind:class=\"{ 'max-md:hidden': ! menu }\"", false)
            ->assertSee('<span class="min-w-0 flex-1 truncate font-serif text-[21px] font-light md:hidden">Zamówienia</span>', false);
    }

    public function test_the_login_page_has_no_menu(): void
    {
        $this->get('/panel/logowanie')
            ->assertOk()
            ->assertDontSee('id="panel-menu"', false)
            ->assertDontSee('aria-controls="panel-menu"', false);
    }

    private function order(OrderStatus $status, PaymentStatus $payment = PaymentStatus::Paid): Order
    {
        $order = Order::create([
            'status' => $status, 'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'phone' => '600100200',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M', 'shipping_gross' => 1600, 'total_gross' => 25500,
            'payment_method' => PaymentMethod::Blik, 'payment_status' => $payment, 'paid_at' => $payment === PaymentStatus::Paid ? now() : null,
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);

        return $order;
    }

    private function idea(CustomOrderStatus $status): CustomOrderRequest
    {
        return CustomOrderRequest::query()->create([
            'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'kind' => BriefKind::Mugs,
            'description' => 'Kubki dla świadków', 'status' => $status,
        ]);
    }

    private function complaint(bool $emailed): ComplaintAnswer
    {
        return ComplaintAnswer::query()->create([
            'order_number' => 'MA-2026-1001', 'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'received_on' => today(),
            'decision' => ComplaintDecision::Accepted, 'letter' => 'Uznaję reklamację.', 'emailed_at' => $emailed ? now() : null,
        ]);
    }

    /** A real queued mail moved to the failed jobs, as the worker leaves it after the last attempt. */
    private function failedMail(): void
    {
        Mail::to('kasia@example.com')->send(new ContactMessageReceived('Ola', 'ola@example.com', null, 'Dzień dobry'));
        $job = DB::table('jobs')->latest('id')->first();
        DB::table('jobs')->where('id', $job->id)->delete();
        app(FailedJobProviderInterface::class)->log('database', 'default', $job->payload, new RuntimeException('Serwer poczty nie odpowiada'));
    }
}
