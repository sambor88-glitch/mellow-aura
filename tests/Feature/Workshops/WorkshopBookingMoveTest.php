<?php

namespace Tests\Feature\Workshops;

use App\Models\User;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Gifts\Enums\RedemptionStatus;
use App\Modules\Gifts\Models\Voucher;
use App\Modules\Gifts\Models\VoucherRedemption;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use App\Modules\Workshops\Actions\MoveBooking;
use App\Modules\Workshops\Actions\SendReminders;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Exceptions\BookingNotMoved;
use App\Modules\Workshops\Mail\BookingMoved;
use App\Modules\Workshops\Mail\BookingReminder;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopBookingMove;
use App\Modules\Workshops\Models\WorkshopDate;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Kasia moves a paid booking to another date of the same workshop (MA-210): only at the same price, without a new
 * payment, with the seat on the old date free at once, a mail with the new date and the address, and the reminder
 * counted from the new date. The payment — money, a workshop voucher or an amount voucher — stays as it was.
 */
class WorkshopBookingMoveTest extends TestCase
{
    use RefreshDatabase;

    private const ADDRESS = 'ul. Przykładowa 1/2, 30-001 Kraków';

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        $this->setting('studio_address', self::ADDRESS);
        $this->setting('contact_email', 'kasia@example.com');
        config(['monitoring.alert_email' => 'alerty@example.com']);
        $this->owner = User::factory()->create();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Warsaw'));
    }

    public function test_only_the_owner_moves_a_booking(): void
    {
        $from = $this->date(seats: 3);
        $to = $this->date(seats: 3, startsAt: '2026-11-07 11:00');
        $booking = $this->book($from);

        $this->post(route('admin.workshops.bookings.move', $booking), ['workshop_date_id' => $to->id])->assertRedirect('/panel/logowanie');
        $this->actingAs(User::factory()->helper()->create())
            ->post(route('admin.workshops.bookings.move', $booking), ['workshop_date_id' => $to->id])
            ->assertForbidden();

        $this->assertSame($from->id, $booking->fresh()->workshop_date_id);
    }

    public function test_a_paid_booking_moves_frees_the_old_seat_tells_her_and_keeps_the_payment(): void
    {
        $from = $this->date(seats: 2);
        $to = $this->date(seats: 3, startsAt: '2026-11-07 11:00');
        $other = $this->date(seats: 3, startsAt: '2026-11-08 11:00', code: 'glazing');
        $booking = $this->book($from, ['seats' => 2, 'expected_total' => 44000]);
        $order = $booking->order;
        $this->assertSame([0, PaymentStatus::Paid], [$from->seatsLeft(), $order->payment_status]);

        $this->actingAs($this->owner)->get(route('admin.workshops.dates.bookings', $from))
            ->assertOk()
            ->assertSee('Przenieś na inny termin')
            ->assertSee('sob. 7 listopada, 11:00 – 13:00 · 3 wolne miejsca')
            ->assertDontSee('value="'.$other->id.'"', false)
            ->assertSee('Wyślij klientce maila z nowym terminem i adresem');

        $this->post(route('admin.workshops.bookings.move', $booking), ['workshop_date_id' => $to->id, 'notify' => '1'])
            ->assertRedirect(route('admin.workshops.dates.bookings', $to).'#zapis-'.$booking->id)
            ->assertSessionHas('panel_status', 'Zapis '.$order->number.' przeniesiony na sob. 7 listopada, 11:00 – 13:00. Miejsce na starym terminie jest wolne. Klient dostanie maila z nowym terminem.');

        $booking->refresh();
        $this->assertSame([$to->id, BookingStatus::Confirmed, null], [$booking->workshop_date_id, $booking->status, $booking->reminded_at]);
        $this->assertNotNull($booking->moved_at);
        $this->assertSame([2, 1], [$from->fresh()->seatsLeft(), $to->fresh()->seatsLeft()]);

        // The order is untouched: the same money, status and line as bought.
        $fresh = $order->fresh();
        $this->assertSame([PaymentStatus::Paid, $order->status, 44000, 0], [$fresh->payment_status, $fresh->status, $fresh->total_gross, (int) $fresh->vouchers_gross]);
        $this->assertSame('sob. 31 października, 11:00 – 13:00', $fresh->items->first()->variant_label);

        $move = WorkshopBookingMove::sole();
        $this->assertSame([$from->id, $to->id], [$move->from_workshop_date_id, $move->to_workshop_date_id]);

        Mail::assertQueued(BookingMoved::class, function (BookingMoved $mail) {
            $mail->assertHasSubject('Nowy termin warsztatu · sob. 7 listopada, 11:00');
            $mail->assertSeeInOrderInHtml(['Przeniosłam Twój zapis', 'sob. 7 listopada, 11:00 – 13:00', 'Poprzedni termin', 'sob. 31 października, 11:00 – 13:00', 'niczego nie dopłacasz', 'Lepienie kubków', 'Liczba osób: 2', '440 zł', self::ADDRESS]);
            $mail->assertSeeInText(self::ADDRESS);
            $mail->assertSeeInHtml('Dzień przed warsztatem przypomnę Ci o nim mailem.');

            return $mail->hasTo('ania@example.com') && $mail->hasReplyTo('kasia@example.com');
        });

        // Kasia sees the move on the new date's list and on the order.
        $this->get(route('admin.workshops.dates.bookings', $to))->assertSee('z sob. 31.10, 11:00 – 13:00 na sob. 7.11, 11:00 – 13:00');
        $this->get(route('admin.workshops.dates.bookings', $from))->assertSee('Nikt się jeszcze nie zapisał');
        $this->get(route('admin.orders.show', $order))->assertSeeInOrder(['Zapis na warsztat', 'Teraz: sob. 7 listopada, 11:00 – 13:00', 'Przeniesiony']);

        // The freed seat books again.
        $this->book($from, ['email' => 'druga@example.com']);
        $this->assertSame(BookingStatus::Confirmed, WorkshopBooking::query()->latest('id')->first()->status);
    }

    public function test_kasia_can_move_without_the_mail(): void
    {
        $booking = $this->book($this->date(seats: 2));
        $to = $this->date(seats: 2, startsAt: '2026-11-07 11:00');

        $this->actingAs($this->owner)->post(route('admin.workshops.bookings.move', $booking), ['workshop_date_id' => $to->id])
            ->assertSessionHas('panel_status', fn (string $status) => ! str_contains($status, 'maila'));

        $this->assertSame($to->id, $booking->fresh()->workshop_date_id);
        Mail::assertNotQueued(BookingMoved::class);
    }

    public function test_a_booking_paid_with_a_workshop_voucher_moves_and_the_voucher_stays_used(): void
    {
        $from = $this->date(seats: 2);
        $to = $this->date(seats: 2, startsAt: '2026-11-07 11:00');
        $voucher = Voucher::factory()->create(['workshop_code' => 'hand_building', 'seats' => 1, 'valid_until' => '2027-10-26']);
        $this->post(route('workshops.booking.store', $from), [...$this->form(), 'payment_method' => 'voucher', 'voucher_code' => $voucher->code, 'blik_code' => null]);
        $booking = WorkshopBooking::sole();
        $this->assertSame($voucher->id, $booking->voucher_id);
        // A workshop voucher books the workshop, not a price: a new price list does not stop the move.
        $this->setPrice(25000);

        $this->actingAs($this->owner)->post(route('admin.workshops.bookings.move', $booking), ['workshop_date_id' => $to->id, 'notify' => '1'])
            ->assertSessionHasNoErrors();

        $booking->refresh();
        $this->assertSame([$to->id, $voucher->id, BookingStatus::Confirmed], [$booking->workshop_date_id, $booking->voucher_id, $booking->status]);
        $this->assertNotNull($voucher->fresh()->redeemed_at);
        $this->assertSame([0, PaymentStatus::Paid], [$booking->order->total_gross, $booking->order->payment_status]);
        Mail::assertQueued(BookingMoved::class, fn (BookingMoved $mail) => $mail->assertSeeInHtml('Liczba osób: 1 · voucher '.$voucher->code) !== null);
    }

    public function test_a_booking_paid_with_an_amount_voucher_moves_and_the_balance_stays(): void
    {
        $from = $this->date(seats: 2);
        $to = $this->date(seats: 2, startsAt: '2026-11-07 11:00');
        $voucher = Voucher::factory()->create(['workshop_code' => null, 'seats' => null, 'amount_minor' => 30000, 'balance_minor' => 30000, 'valid_until' => '2027-10-26']);
        $this->post(route('workshops.booking.store', $from), $this->form(['vouchers' => [$voucher->code], 'expected_vouchers' => 22000, 'blik_code' => null]));
        $booking = WorkshopBooking::sole();
        $this->assertSame([BookingStatus::Confirmed, 8000], [$booking->status, $voucher->fresh()->balance_minor]);

        $this->actingAs($this->owner)->post(route('admin.workshops.bookings.move', $booking), ['workshop_date_id' => $to->id])
            ->assertSessionHasNoErrors();

        $this->assertSame($to->id, $booking->fresh()->workshop_date_id);
        $this->assertSame(8000, $voucher->fresh()->balance_minor);
        $this->assertSame(RedemptionStatus::Used, VoucherRedemption::sole()->status);
        $this->assertSame([22000, 0, PaymentStatus::Paid], [(int) $booking->order->fresh()->vouchers_gross, $booking->order->fresh()->amountDue(), $booking->order->fresh()->payment_status]);
    }

    public function test_a_booking_whose_workshop_costs_otherwise_now_is_not_moved(): void
    {
        $from = $this->date(seats: 2);
        $to = $this->date(seats: 2, startsAt: '2026-11-07 11:00');
        $booking = $this->book($from);
        $this->setPrice(25000);

        $this->actingAs($this->owner)->get(route('admin.workshops.dates.bookings', $from))
            ->assertSee('Warsztat kosztuje teraz 250 zł za osobę, a ten zapis był za 220 zł. Przenoszę tylko na termin w tej samej cenie — bez dopłat i zwrotów różnicy.')
            ->assertDontSee('Przenieś zapis</button>', false);

        $this->post(route('admin.workshops.bookings.move', $booking), ['workshop_date_id' => $to->id])
            ->assertRedirect(route('admin.workshops.dates.bookings', $from).'#zapis-'.$booking->id)
            ->assertSessionHasErrors(['workshop_date_id'], errorBag: 'move'.$booking->id);

        $this->assertSame($from->id, $booking->fresh()->workshop_date_id);
        $this->assertSame(0, WorkshopBookingMove::count());
    }

    public function test_a_full_date_another_workshop_a_past_or_cancelled_booking_are_refused(): void
    {
        $from = $this->date(seats: 3);
        $full = $this->date(seats: 1, startsAt: '2026-11-07 11:00');
        $wheel = $this->date(seats: 3, startsAt: '2026-11-08 11:00', code: 'glazing');
        $booking = $this->book($from, ['seats' => 2, 'expected_total' => 44000]);
        $move = app(MoveBooking::class);

        $this->actingAs($this->owner)->get(route('admin.workshops.dates.bookings', $from))
            ->assertSee('<option value="'.$full->id.'" disabled>sob. 7 listopada, 11:00 – 13:00 · za mało miejsc (ostatnie miejsce)</option>', false);

        $this->assertRefused(fn () => $move($booking, $full), 'Na sob. 7 listopada, 11:00 – 13:00 nie ma już 2 wolnych miejsc.');
        $this->assertRefused(fn () => $move($booking, $wheel), 'Przenieść można tylko na termin tego samego warsztatu.');
        $this->assertRefused(fn () => $move($booking, $from), 'Zapis już jest na tym terminie.');

        $later = $this->date(seats: 3, startsAt: '2026-11-21 11:00');
        $this->travelTo(CarbonImmutable::parse('2026-10-31 14:00', 'Europe/Warsaw'));
        $this->assertRefused(fn () => $move($booking, $later), 'Ten termin już się odbył — zapisu nie da się przenieść.');

        $this->travelBack();
        $this->travelTo(CarbonImmutable::parse('2026-10-26 12:00', 'Europe/Warsaw'));
        $this->post(route('admin.workshops.bookings.cancel', $booking));
        $this->assertRefused(fn () => $move($booking->fresh(), $later), 'Przenieść można tylko opłacony zapis.');

        $this->assertSame(0, WorkshopBookingMove::count());
        Mail::assertNotQueued(BookingMoved::class);
    }

    public function test_the_reminder_goes_again_before_the_new_date(): void
    {
        $from = $this->date(seats: 2);
        $to = $this->date(seats: 2, startsAt: '2026-11-07 11:00');
        $booking = $this->book($from);

        // The reminder for the old date already went.
        $this->travelTo(CarbonImmutable::parse('2026-10-30 12:00', 'Europe/Warsaw'));
        $this->assertSame(1, app(SendReminders::class)());
        $this->assertNotNull($booking->fresh()->reminded_at);

        app(MoveBooking::class)($booking, $to, notify: false);
        $this->assertNull($booking->fresh()->reminded_at);

        $this->travelTo(CarbonImmutable::parse('2026-11-06 12:00', 'Europe/Warsaw'));
        $this->assertSame(1, app(SendReminders::class)());
        Mail::assertQueued(BookingReminder::class, 2);
    }

    public function test_a_booking_moved_to_tomorrow_gets_no_reminder_on_top_of_the_mail(): void
    {
        $booking = $this->book($this->date(seats: 2));
        $tomorrow = $this->date(seats: 2, startsAt: '2026-10-27 10:00');

        app(MoveBooking::class)($booking, $tomorrow);

        $this->assertSame(0, app(SendReminders::class)());
        Mail::assertQueued(BookingMoved::class, fn (BookingMoved $mail) => $mail->assertDontSeeInHtml('przypomnę Ci') !== null);
    }

    public function test_the_mail_shows_in_the_panel_with_its_editable_texts(): void
    {
        $this->actingAs($this->owner)->get(route('admin.mail-texts.edit'))
            ->assertOk()
            ->assertSee('Przeniesienie zapisu na inny termin');
    }

    private function assertRefused(callable $move, string $message): void
    {
        try {
            $move();
            $this->fail('The move went through: '.$message);
        } catch (BookingNotMoved $refused) {
            $this->assertSame($message, $refused->getMessage());
        }
    }

    private function setPrice(int $price): void
    {
        $types = Setting::find('workshop_types')->value;

        foreach ($types as $i => $type) {
            if (($type['code'] ?? null) === 'hand_building') {
                $types[$i]['price_gross'] = $price;
            }
        }

        $this->setting('workshop_types', $types);
    }

    private function setting(string $key, mixed $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        app(Settings::class)->refresh();
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function book(WorkshopDate $date, array $overrides = []): WorkshopBooking
    {
        $this->flushSession();
        $this->post(route('workshops.booking.store', $date), $this->form($overrides));

        return WorkshopBooking::query()->latest('id')->firstOrFail();
    }

    private function date(int $seats, string $startsAt = '2026-10-31 11:00', string $code = 'hand_building'): WorkshopDate
    {
        return WorkshopDate::factory()->create([
            'workshop_code' => $code,
            'starts_at' => $startsAt,
            'ends_at' => CarbonImmutable::parse($startsAt)->addHours(2),
            'seats' => $seats,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_filter([
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600 100 200',
            'seats' => 1,
            'payment_method' => 'blik',
            'blik_code' => '123456',
            'expected_total' => 22000,
            'accept_terms' => '1',
            ...$overrides,
        ], fn (mixed $value) => $value !== null);
    }
}
