<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Checkout\Enums\ComplaintDecision;
use App\Modules\Checkout\Enums\MediationConsent;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\ComplaintAnswered;
use App\Modules\Checkout\Models\ComplaintAnswer;
use App\Modules\Checkout\Models\Order;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AdminComplaintsTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->travelTo(now()->setDate(2026, 11, 20)->setTime(12, 0));
        $this->owner = User::factory()->create();
        Setting::create(['key' => 'company_name', 'value' => 'MellowAura Katarzyna Samborska']);
        Setting::create(['key' => 'contact_email', 'value' => 'kasia@mellow-aura.com']);
    }

    public function test_guests_are_sent_to_the_login(): void
    {
        $this->get('/panel/reklamacje')->assertRedirect('/panel/logowanie');
        $this->post('/panel/reklamacje')->assertRedirect('/panel/logowanie');
    }

    public function test_the_order_page_leads_here_with_the_customer_filled_in(): void
    {
        $order = $this->order();

        $this->actingAs($this->owner)
            ->get('/panel/zamowienia/'.$order->number)
            ->assertSee('href="'.route('admin.complaints.index', ['zamowienie' => $order->number]).'#odpowiedz"', false);

        $this->get('/panel/reklamacje?zamowienie='.$order->number)
            ->assertOk()
            ->assertSee('>Reklamacje</a>', false)
            ->assertSee('value="Anna Nowak"', false)
            ->assertSee('value="ania@example.com"', false)
            ->assertSee('value="'.$order->number.'"', false)
            ->assertSee('Jeszcze nic tu nie ma');
    }

    public function test_a_rejection_needs_a_reason_and_the_statement_on_mediation(): void
    {
        $this->actingAs($this->owner)
            ->followingRedirects()
            ->post('/panel/reklamacje', [...$this->form(), 'decision' => 'rejected', 'details' => '', 'mediation' => '', 'intent' => 'preview'])
            ->assertSee('Napisz, dlaczego nie uznajesz reklamacji albo którą część uznajesz')
            ->assertSee('Zaznacz, czy zgadzasz się na mediację — bez tego prawo uznaje, że się zgadzasz');

        $this->post('/panel/reklamacje', [...$this->form(), 'decision' => 'accepted', 'remedy' => ''])
            ->assertSessionHasErrors(['remedy' => 'Wybierz, jak załatwisz reklamację: naprawa, wymiana, obniżenie ceny albo zwrot']);

        $this->post('/panel/reklamacje', [...$this->form(), 'received_on' => '2026-11-21'])
            ->assertSessionHasErrors(['received_on' => 'Ta data jest w przyszłości — wpisz dzień, w którym reklamacja doszła']);
    }

    public function test_the_preview_shows_the_letter_and_the_deadline_before_anything_is_sent(): void
    {
        Mail::fake();
        $order = $this->order();

        $this->actingAs($this->owner)
            ->followingRedirects()
            ->post('/panel/reklamacje', [...$this->form(), 'order_number' => $order->number, 'decision' => 'rejected', 'details' => 'Pęknięcie powstało od uderzenia w brzeg.', 'mediation' => 'refuses', 'intent' => 'preview'])
            ->assertSeeInOrder([
                'Odpowiedz do 26.11.2026.',
                'Dzień dobry,',
                'dziękuję za reklamację, która doszła do mnie 12 listopada 2026 i dotyczy zamówienia '.$order->number.'.',
                'Nie uznaję reklamacji.',
                'Pęknięcie powstało od uderzenia w brzeg.',
                'Nie zgadzam się na udział w pozasądowym postępowaniu w sprawie rozwiązania tego sporu.',
                'O innych sposobach dochodzenia roszczeń piszę w regulaminie, w §14: '.route('content.terms').'#regulamin-14-pozasadowe-rozwiazywanie-sporow',
                'Katarzyna Samborska',
                'MellowAura Katarzyna Samborska',
            ])
            ->assertSee('Wyślij odpowiedź e-mailem');

        Mail::assertNothingSent();
        $this->assertSame(0, ComplaintAnswer::count());
    }

    public function test_sending_mails_the_letter_and_keeps_it_word_for_word(): void
    {
        Mail::fake();
        $order = $this->order();

        $this->actingAs($this->owner)
            ->post('/panel/reklamacje', [...$this->form(), 'order_number' => strtolower($order->number), 'decision' => 'partly_accepted', 'details' => 'Obniżę cenę o 40 zł, ale drugi kubek był cały.', 'mediation' => 'agrees', 'remedy' => 'refund', 'intent' => 'send'])
            ->assertRedirect('/panel/reklamacje')
            ->assertSessionHas('panel_status', 'Odpowiedź wysłana do ania@example.com. Kopia jest na liście.');

        $answer = ComplaintAnswer::sole();
        $this->assertSame([$order->id, ComplaintDecision::PartlyAccepted, null, MediationConsent::Agrees], [$answer->order_id, $answer->decision, $answer->remedy, $answer->mediation]);
        $this->assertStringContainsString('Uznaję reklamację w części.', $answer->letter);
        $this->assertStringContainsString('Małopolski Wojewódzki Inspektor Inspekcji Handlowej w Krakowie, ul. Ujastek 7, 31-752 Kraków', $answer->letter);
        $this->assertNotNull($answer->emailed_at);

        Mail::assertSent(ComplaintAnswered::class, function (ComplaintAnswered $mail) use ($order) {
            return $mail->hasTo('ania@example.com')
                && $mail->hasReplyTo('kasia@mellow-aura.com')
                && $mail->envelope()->subject === 'Odpowiedź na reklamację — '.$order->number
                && str_contains($mail->render(), 'Uznaję reklamację w części.');
        });

        $this->get('/panel/reklamacje')
            ->assertSeeInOrder(['Wysłane odpowiedzi', 'Anna Nowak', $order->number, 'reklamacja z 12.11.2026', 'Uznaję w części', 'zgadzam się na mediację w Inspekcji Handlowej', 'Wysłana e-mailem', 'Treść odpowiedzi', 'Obniżę cenę o 40 zł']);
    }

    public function test_an_accepted_complaint_names_the_remedy_and_carries_no_mediation_statement(): void
    {
        Mail::fake();

        $this->actingAs($this->owner)
            ->post('/panel/reklamacje', [...$this->form(), 'decision' => 'accepted', 'remedy' => 'replacement', 'details' => '', 'mediation' => 'refuses', 'intent' => 'send']);

        $answer = ComplaintAnswer::sole();
        $this->assertStringContainsString('Uznaję reklamację. Wymienię produkt na nową sztukę. Odbiór i wysyłka nowej są na mój koszt.', $answer->letter);
        $this->assertStringNotContainsString('pozasądow', $answer->letter);
        $this->assertNull($answer->mediation);
        $this->assertStringNotContainsString('dotyczy zamówienia', $answer->letter);
    }

    /**
     * @return array<string, string>
     */
    private function form(): array
    {
        return ['name' => 'Anna Nowak', 'email' => 'ania@example.com', 'order_number' => '', 'received_on' => '2026-11-12', 'decision' => 'accepted', 'remedy' => 'repair', 'details' => '', 'mediation' => '', 'intent' => 'preview'];
    }

    private function order(): Order
    {
        $order = Order::create([
            'status' => OrderStatus::InProgress,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600100200',
            'shipping_method' => 'parcel_locker',
            'shipping_gross' => 1600,
            'total_gross' => 25500,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now(),
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);

        return $order;
    }
}
