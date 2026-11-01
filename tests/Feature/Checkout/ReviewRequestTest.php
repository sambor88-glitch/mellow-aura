<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Checkout\Actions\SendReviewRequests;
use App\Modules\Checkout\Enums\OrderStatus;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Enums\PaymentStatus;
use App\Modules\Checkout\Mail\ReviewRequest;
use App\Modules\Checkout\Models\Complaint;
use App\Modules\Checkout\Models\Order;
use App\Modules\Checkout\Models\Withdrawal;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\PersonalData;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * MA-329: one mail, a few days after the delivery, asking for a review on the Google Business Profile.
 */
class ReviewRequestTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://g.page/r/CTEST123/review';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Mail::fake();
        Setting::create(['key' => 'review_request_url', 'value' => self::URL]);
        Setting::create(['key' => 'review_request_days', 'value' => 7]);
        Setting::create(['key' => 'contact_email', 'value' => 'kontakt@example.com']);
    }

    public function test_the_customer_gets_one_mail_the_set_number_of_days_after_the_delivery(): void
    {
        $order = $this->order(['completed_at' => now()->subDays(7)->subHour()]);

        $this->artisan('checkout:send-review-requests')
            ->expectsOutput('Próśb o opinię w kolejce: 1')
            ->assertSuccessful();

        Mail::assertQueued(ReviewRequest::class, fn (ReviewRequest $mail) => $mail->hasTo('ania@example.com') && $mail->order->is($order) && $mail->reviewUrl === self::URL);
        $this->assertNotNull($order->fresh()->review_requested_at);

        // The next day, and after a detour through „Problem” and back, she hears nothing more.
        $this->travel(1)->day();
        $order->update(['status' => OrderStatus::Problem]);
        $order->update(['status' => OrderStatus::Completed, 'completed_at' => now()->subDays(7)]);
        $this->artisan('checkout:send-review-requests')->expectsOutput('Próśb o opinię w kolejce: 0');

        Mail::assertQueuedCount(1);
    }

    public function test_the_mail_asks_for_an_honest_review_without_any_reward(): void
    {
        $order = $this->order(['completed_at' => now()->subDays(7)]);

        $mail = new ReviewRequest($order, self::URL);
        $mail->assertHasSubject('Jak się u Ciebie sprawdza zamówienie '.$order->number.'?');
        $mail->assertHasReplyTo('kontakt@example.com');
        $mail->assertSeeInOrderInHtml(['Jak się u Ciebie sprawdza?', 'Twoje zamówienie', $order->number, 'jest już u Ciebie', self::URL, 'Napisz opinię w Google', 'także krytyczna', 'Odpisz na tego maila']);
        $mail->assertSeeInText('Napisz opinię w Google: '.self::URL);
        $mail->assertDontSeeInHtml('rabat');
        $this->assertSame('Prośba o opinię o zamówieniu '.$order->number, $mail->description());
    }

    public function test_an_order_from_the_english_shop_gets_the_mail_in_english(): void
    {
        $order = $this->order(['locale' => 'en', 'currency' => 'EUR', 'completed_at' => now()->subDays(8)]);

        app(SendReviewRequests::class)();

        Mail::assertQueued(ReviewRequest::class, function (ReviewRequest $mail) use ($order) {
            $mail->assertHasSubject('How are you getting on with order '.$order->number.'?');
            $mail->assertSeeInHtml('Write a Google review');
            $mail->assertSeeInText('Write a Google review: '.self::URL);

            return $mail->locale === 'en';
        });
    }

    public function test_without_the_link_or_the_number_of_days_nothing_goes_out(): void
    {
        $this->order(['completed_at' => now()->subDays(7)]);

        Setting::where('key', 'review_request_url')->update(['value' => null]);
        $this->assertSame(0, app(SendReviewRequests::class)());

        Setting::where('key', 'review_request_url')->update(['value' => json_encode(self::URL)]);
        Setting::where('key', 'review_request_days')->update(['value' => null]);
        app(Settings::class)->refresh();
        $this->assertSame(0, app(SendReviewRequests::class)());

        Mail::assertNothingQueued();
    }

    public function test_only_orders_completed_within_the_catch_up_week_get_the_mail(): void
    {
        $this->order(['completed_at' => now()->subDays(6)]);
        $this->order(['completed_at' => now()->subDays(7 + SendReviewRequests::CATCH_UP_DAYS)->subHour()]);
        $caughtUp = $this->order(['completed_at' => now()->subDays(7 + SendReviewRequests::CATCH_UP_DAYS - 1)]);

        $this->assertSame(1, app(SendReviewRequests::class)());

        Mail::assertQueued(ReviewRequest::class, fn (ReviewRequest $mail) => $mail->order->is($caughtUp));
    }

    public function test_no_mail_after_a_problem_a_withdrawal_a_complaint_or_an_erasure(): void
    {
        $long = ['completed_at' => now()->subDays(8)];

        $this->order(['status' => OrderStatus::Problem, 'problem_note' => 'Paczka wróciła', ...$long]);
        $this->order(['status' => OrderStatus::Shipped, ...$long]);

        $withdrawn = $this->order($long);
        Withdrawal::create(['order_id' => $withdrawn->id, 'order_number' => $withdrawn->number, 'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'scope' => 'whole', 'submitted_at' => now()]);

        // A complaint typed in by hand, matched by the number only.
        $complained = $this->order($long);
        Complaint::create(['order_number' => $complained->number, 'name' => 'Anna Nowak', 'email' => 'ania@example.com', 'received_on' => now()->toDateString(), 'description' => 'Pęknięty kubek']);

        $this->order(['email' => PersonalData::ERASED_EMAIL, ...$long]);
        // A voucher sent as a PDF had nothing to deliver.
        $this->order(['shipping_method' => 'email', 'locker_code' => null, ...$long]);

        $this->assertSame(0, app(SendReviewRequests::class)());
        Mail::assertNothingQueued();
    }

    public function test_the_scheduler_runs_it_once_a_day(): void
    {
        $event = collect($this->app->make(Schedule::class)->events())
            ->first(fn ($event) => str_contains((string) $event->command, 'checkout:send-review-requests'));

        $this->assertNotNull($event);
        $this->assertSame('15 10 * * *', $event->expression);
    }

    public function test_kasia_sets_the_link_and_the_days_in_the_settings(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/panel/ustawienia')
            ->assertOk()
            ->assertSee('Prośba o opinię w Google')
            ->assertSee('value="'.self::URL.'"', false);

        $this->put('/panel/ustawienia/opinie', ['review_request_url' => ' g.page/r/CNEW/review ', 'review_request_days' => '10'])
            ->assertRedirect('/panel/ustawienia#opinie')
            ->assertSessionHas('panel_status', 'Zapisane. Prośba o opinię wyjdzie 10 dni po zakończeniu zamówienia.');

        $this->assertSame('https://g.page/r/CNEW/review', Setting::where('key', 'review_request_url')->value('value'));
        $this->assertSame(10, Setting::where('key', 'review_request_days')->value('value'));

        $this->put('/panel/ustawienia/opinie', ['review_request_url' => '', 'review_request_days' => '10'])
            ->assertSessionHas('panel_status', 'Zapisane. Bez linku i liczby dni prośba o opinię nie wychodzi.');
        $this->assertNull(Setting::where('key', 'review_request_url')->value('value'));

        $this->put('/panel/ustawienia/opinie', ['review_request_url' => 'ftp://example.com', 'review_request_days' => '0'])
            ->assertSessionHasErrors(['review_request_url', 'review_request_days'], null, 'opinie');
    }

    public function test_the_mail_is_on_kasias_list_of_mails_with_a_preview(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/panel/maile')
            ->assertOk()
            ->assertSee('Prośba o opinię')
            ->assertSee('name="mail_review_request_intro_en"', false);

        $this->put('/panel/maile/podglad', ['preview' => 'review_request:pl', 'mail_review_request_intro' => 'Zamówienie {numer} już u Ciebie.'])
            ->assertOk()
            ->assertSee('już u Ciebie.')
            ->assertSee('Napisz opinię w Google')
            ->assertSee(self::URL);
    }

    private function order(array $attributes = []): Order
    {
        $order = Order::create([
            'status' => OrderStatus::Completed,
            'name' => 'Anna Nowak',
            'email' => 'ania@example.com',
            'phone' => '600100200',
            'shipping_method' => 'parcel_locker', 'locker_code' => 'KRA01M',
            'shipping_gross' => 1600,
            'total_gross' => 49400,
            'payment_method' => PaymentMethod::Blik,
            'payment_status' => PaymentStatus::Paid,
            'paid_at' => now()->subDays(14),
            'shipped_at' => now()->subDays(10),
            ...$attributes,
        ]);
        $order->update(['number' => 'MA-2026-'.(1000 + $order->id)]);
        $order->items()->create(['product_name' => 'Wazony', 'variant_label' => 'Niski 16 cm', 'quantity' => 1, 'unit_price_gross' => 23900]);

        return $order;
    }
}
