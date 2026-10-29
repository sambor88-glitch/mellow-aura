<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Checkout\Actions\AnonymizeCustomer;
use App\Modules\Checkout\Enums\ComplaintStatus;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\ComplaintAnswered;
use App\Modules\Checkout\Models\Complaint;
use App\Modules\Checkout\Models\ComplaintAnswer;
use App\Modules\Checkout\Models\Order;
use App\Modules\Customers\Support\CustomerKey;
use App\Modules\Settings\Models\Setting;
use App\Modules\Shared\Support\PersonalData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Complaints saved the day they arrive by e-mail (MA-261): the 14 days show from then on, in „Reklamacje”, in the menu
 * and on „Na dziś”, until the answer closes them.
 */
class AdminComplaintRecordsTest extends TestCase
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

    public function test_guests_cannot_save_a_complaint(): void
    {
        $this->post('/panel/reklamacje/nowa')->assertRedirect('/panel/logowanie');
        $this->assertSame(0, Complaint::count());
    }

    public function test_a_complaint_from_the_order_page_is_saved_with_the_order_filled_in(): void
    {
        $order = $this->order();

        $this->actingAs($this->owner)
            ->get('/panel/reklamacje?zamowienie='.$order->number)
            ->assertOk()
            ->assertSee('Nic nie czeka')
            ->assertSee('id="zapisz" class="', false)
            ->assertSee('value="'.$order->number.'"', false);

        $this->post('/panel/reklamacje/nowa', ['name' => 'Anna Nowak', 'email' => 'ania@example.com', 'order_number' => strtolower($order->number), 'received_on' => '2026-11-18', 'description' => 'Miska pękła po pierwszym myciu.'])
            ->assertRedirect('/panel/reklamacje#czekaja')
            ->assertSessionHas('panel_status', 'Reklamacja zapisana. Odpowiedz do 2.12.2026.');

        $complaint = Complaint::sole();
        $this->assertSame([$order->id, $order->number, ComplaintStatus::Open, '2026-11-18'], [$complaint->order_id, $complaint->order_number, $complaint->status, $complaint->received_on->toDateString()]);
    }

    public function test_a_complaint_needs_who_when_and_what(): void
    {
        $this->actingAs($this->owner)
            ->post('/panel/reklamacje/nowa', ['name' => '', 'email' => 'ania', 'received_on' => '2026-11-21', 'description' => ''])
            ->assertRedirect('/panel/reklamacje#zapisz')
            ->assertSessionHasErrorsIn('complaint', [
                'name' => 'Wpisz imię i nazwisko osoby, która złożyła reklamację',
                'email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
                'received_on' => 'Ta data jest w przyszłości — wpisz dzień, w którym reklamacja doszła',
                'description' => 'Napisz krótko, co klientka zgłasza — np. „pęknięta miska, zdjęcie w mailu”',
            ]);

        $this->assertSame(0, Complaint::count());
    }

    public function test_open_complaints_are_listed_oldest_first_with_the_deadline_highlighted(): void
    {
        $this->complaint('Ola Lis', '2026-11-18');
        $this->complaint('Ewa Kot', '2026-11-04');
        $this->complaint('Iza Maj', '2026-11-08');
        $this->complaint('Jan Bąk', '2026-11-01', ComplaintStatus::Answered);

        $this->actingAs($this->owner)
            ->get('/panel/reklamacje')
            ->assertOk()
            ->assertSeeInOrder([
                'Czekają na odpowiedź',
                'Ewa Kot', 'Termin minął 18.11',
                'Iza Maj', 'Odpowiedz do 22.11 — zostały 2 dni',
                'Ola Lis', 'Odpowiedz do 2.12 — zostało 12 dni',
                'Zapisz reklamację',
            ])
            ->assertDontSee('Jan Bąk');

        $this->assertSame(['termin minął 18.11', 'odpowiedz do 22.11 — zostały 2 dni', 'odpowiedz do 2.12 — zostało 12 dni'],
            Complaint::query()->open()->orderBy('received_on')->get()->map->deadlineLabel()->all());
        $this->assertSame([true, true, false], Complaint::query()->open()->orderBy('received_on')->get()->map->dueSoon()->all());
    }

    public function test_answering_fills_the_form_from_the_complaint_and_closes_it(): void
    {
        Mail::fake();
        $complaint = $this->complaint('Anna Nowak', '2026-11-12');

        $this->actingAs($this->owner)
            ->get('/panel/reklamacje?reklamacja='.$complaint->id)
            ->assertOk()
            ->assertSee('name="complaint_id" value="'.$complaint->id.'"', false)
            ->assertSee('value="Anna Nowak"', false)
            ->assertSee('value="2026-11-12"', false)
            ->assertSee('Pęknięta miska, zdjęcie w mailu.');

        $form = ['complaint_id' => $complaint->id, 'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'order_number' => '', 'received_on' => '2026-11-12', 'decision' => 'accepted', 'remedy' => 'repair', 'details' => '', 'mediation' => ''];

        // „Pokaż list” keeps the complaint on the form.
        $this->followingRedirects()
            ->post('/panel/reklamacje', [...$form, 'intent' => 'preview'])
            ->assertSee('name="complaint_id" value="'.$complaint->id.'"', false);

        $this->post('/panel/reklamacje', [...$form, 'intent' => 'send'])->assertRedirect('/panel/reklamacje');

        $this->assertSame(ComplaintStatus::Answered, $complaint->fresh()->status);
        $this->assertSame($complaint->id, ComplaintAnswer::sole()->complaint_id);
        Mail::assertSent(ComplaintAnswered::class);

        $this->get('/panel/reklamacje')->assertSee('Nic nie czeka');

        // A second answer to the same complaint is stopped.
        $this->post('/panel/reklamacje', [...$form, 'intent' => 'send'])
            ->assertSessionHasErrors(['complaint_id' => 'Ta reklamacja ma już odpowiedź — znajdziesz ją na liście wysłanych']);
        $this->assertSame(1, ComplaintAnswer::count());
    }

    public function test_the_menu_and_the_dashboard_count_open_complaints_with_their_deadline(): void
    {
        $this->complaint('Ewa Kot', '2026-11-08');
        $this->complaint('Ola Lis', '2026-11-18');
        $this->complaint('Jan Bąk', '2026-11-01', ComplaintStatus::Answered);

        $this->actingAs($this->owner)
            ->get('/panel')
            ->assertOk()
            ->assertSee(', 2 bez odpowiedzi', false)
            ->assertSeeInOrder(['2', 'reklamacje', 'odpowiedz do 22.11 — zostały 2 dni', 'Odpowiedz →'])
            ->assertSeeInOrder(['Wymaga Twojej reakcji', 'reklamacja', 'Ewa Kot', 'Odpowiedz do 22.11 — zostały 2 dni', 'reklamacja', 'Ola Lis', 'Odpowiedz do 2.12 — zostało 12 dni'])
            ->assertSee(route('admin.complaints.index', ['reklamacja' => Complaint::query()->where('name', 'Ewa Kot')->value('id')]).'#odpowiedz', false);
    }

    public function test_a_helper_does_not_see_complaints_on_the_dashboard(): void
    {
        $this->complaint('Ewa Kot', '2026-11-08');

        $this->actingAs(User::factory()->helper()->create())
            ->get('/panel')
            ->assertOk()
            ->assertDontSee('Ewa Kot')
            ->assertDontSee(route('admin.complaints.index'), false);
    }

    public function test_complaints_are_in_her_card_her_copy_and_are_anonymized_with_her(): void
    {
        $open = $this->complaint('Anna Nowak', '2026-11-18');

        $this->actingAs($this->owner)
            ->get('/panel/klientki/'.CustomerKey::of('ania@example.com'))
            ->assertOk()
            ->assertSeeInOrder(['Odstąpienia i reklamacje', '18.11.2026', 'Reklamacja', 'odpowiedz do 2.12 — zostało 12 dni', 'Pęknięta miska'])
            // An open complaint still needs her address for the answer.
            ->assertSee('Reklamacja z 18.11.2026 — odpowiedz do 2.12 — zostało 12 dni');

        $copy = json_decode($this->post('/panel/klientki/'.CustomerKey::of('ania@example.com').'/kopia-danych')->getContent(), true);
        $this->assertSame('Pęknięta miska, zdjęcie w mailu.', $copy['received_complaints'][0]['description']);

        $open->update(['status' => ComplaintStatus::Answered]);
        $result = app(AnonymizeCustomer::class)('Ania@example.com');

        $this->assertSame(1, $result['received_complaints']);
        $this->assertSame(['Anna Nowak', PersonalData::ERASED_EMAIL, 'Pęknięta miska, zdjęcie w mailu.'], [$open->fresh()->name, $open->fresh()->email, $open->fresh()->description]);
    }

    private function complaint(string $name, string $receivedOn, ComplaintStatus $status = ComplaintStatus::Open): Complaint
    {
        return Complaint::query()->create([
            'name' => $name, 'email' => $name === 'Anna Nowak' ? 'ania@example.com' : str($name)->ascii()->lower()->replace(' ', '.').'@example.com',
            'received_on' => $receivedOn, 'description' => 'Pęknięta miska, zdjęcie w mailu.', 'status' => $status,
        ]);
    }

    private function order(): Order
    {
        $order = Order::create([
            'status' => OrderStatus::InProgress,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600100200',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M',
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
