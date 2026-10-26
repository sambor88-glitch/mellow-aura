<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Modules\Admin\Enums\Role;
use App\Modules\Admin\Mail\PanelPasswordLink;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\Withdrawal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PanelAccountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_helper_sees_and_moves_orders_and_nothing_else(): void
    {
        $helper = User::factory()->helper()->create();
        $order = Order::create([
            'status' => OrderStatus::InProgress, 'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'phone' => '600100200',
            'shipping_method' => 'parcel_locker', 'shipping_gross' => 1600, 'total_gross' => 25500,
            'payment_method' => PaymentMethod::Blik, 'payment_status' => PaymentStatus::Paid, 'paid_at' => now(),
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);
        Withdrawal::create(['order_id' => $order->id, 'order_number' => $order->number, 'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'scope' => 'whole', 'submitted_at' => now()]);
        $this->actingAs($helper);

        $this->get('/panel')
            ->assertOk()
            ->assertSee('Zamówienia do spakowania i wysłania')
            ->assertSee('href="'.route('admin.orders.index').'"', false)
            ->assertDontSee('href="'.route('admin.products.index').'"', false)
            ->assertDontSee('href="'.route('admin.settings.edit').'"', false)
            ->assertDontSee('href="'.route('admin.accounts.index').'"', false);

        $this->get('/panel/zamowienia')->assertOk();
        $this->get('/panel/zamowienia/'.$order->number)
            ->assertOk()
            ->assertSee('Wysłane — powiadom klientkę')
            ->assertDontSee('Wszystkie odstąpienia')
            ->assertDontSee('Odpowiedz na reklamację');
        Mail::fake();
        $this->patch('/panel/zamowienia/'.$order->number.'/status', ['status' => 'completed'])->assertRedirect();

        foreach (['/panel/produkty', '/panel/ustawienia', '/panel/konta', '/panel/odstapienia', '/panel/reklamacje', '/panel/niewyslane-maile', '/panel/tresci', '/panel/prezenty'] as $screen) {
            $this->get($screen)->assertForbidden();
        }
        $this->post('/panel/konta', ['name' => 'Ktoś', 'email' => 'ktos@example.com', 'role' => 'owner'])->assertForbidden();
        $this->assertSame(1, User::count());
    }

    public function test_the_owner_adds_a_helper_who_sets_her_own_password(): void
    {
        Mail::fake();
        $owner = User::factory()->create(['name' => 'Kasia', 'email' => 'kasia@example.com']);
        $this->actingAs($owner);

        $this->get('/panel')->assertSee('href="'.route('admin.accounts.index').'"', false);
        $this->get('/panel/konta')
            ->assertOk()
            ->assertSeeInOrder(['Kasia', '· to Ty', 'kasia@example.com', 'Właścicielka — cały panel', 'Nowe konto', 'Pomoc przy zamówieniach']);

        $this->from('/panel/konta')
            ->post('/panel/konta', ['name' => '', 'email' => 'kasia@example.com', 'role' => 'helper'])
            ->assertSessionHasErrors(['name' => 'Wpisz imię — tak zobaczysz to konto na liście', 'email' => 'To konto już jest na liście']);

        $this->post('/panel/konta', ['name' => 'Ola', 'email' => ' Ola@Example.com ', 'role' => 'helper'])
            ->assertRedirect('/panel/konta')
            ->assertSessionHas('panel_status', 'Konto dodane — link do hasła poszedł na ola@example.com.');

        $helper = User::query()->where('email', 'ola@example.com')->sole();
        $this->assertSame(Role::Helper, $helper->role);
        Mail::assertQueued(PanelPasswordLink::class, function (PanelPasswordLink $mail) {
            $mail->assertHasSubject('Dostęp do panelu MellowAury');
            $mail->assertSeeInHtml('Masz konto w panelu sklepu MellowAura: ola@example.com');
            $mail->assertSeeInText('Gdy wygaśnie, na stronie logowania kliknij „Nie pamiętasz hasła?”.');

            return $mail->hasTo('ola@example.com') && $mail->invitation;
        });

        $this->post('/panel/konta/'.$helper->id.'/link')->assertSessionHas('panel_status', 'Nowy link do hasła poszedł na ola@example.com.');
        Mail::assertQueuedCount(2);

        $this->delete('/panel/konta/'.$owner->id)->assertSessionHas('panel_status', 'Tego konta nie usunę — panel musi mieć właścicielkę.');
        $this->delete('/panel/konta/'.$helper->id)->assertSessionHas('panel_status', 'Dostęp usunięty — ola@example.com nie wejdzie już do panelu.');
        $this->assertSame([$owner->id], User::query()->pluck('id')->all());
    }

    public function test_accounts_from_before_the_roles_keep_the_whole_panel_and_the_command_can_add_a_helper(): void
    {
        $this->assertTrue(User::factory()->create()->fresh()->isOwner());

        $this->artisan('admin:user', ['email' => 'pomoc@example.com', '--name' => 'Pomoc', '--role' => 'helper'])
            ->expectsQuestion('Password (at least 12 characters)', 'bardzo-dlugie-haslo')
            ->assertSuccessful();
        $this->assertSame(Role::Helper, User::query()->where('email', 'pomoc@example.com')->sole()->role);

        $this->artisan('admin:user', ['email' => 'pomoc@example.com', '--role' => 'szefowa'])
            ->expectsQuestion('Password (at least 12 characters)', 'bardzo-dlugie-haslo')
            ->assertFailed();
    }
}
