<?php

namespace Tests\Feature\CustomOrders;

use App\Models\User;
use App\Modules\Checkout\Models\Order;
use App\Modules\CustomOrders\Actions\AnonymizeCustomer;
use App\Modules\CustomOrders\Actions\EraseExpiredRequests;
use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Enums\ErasureReason;
use App\Modules\CustomOrders\Enums\PaymentLinkStatus;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\CustomOrders\Models\PaymentLink;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Shared\Support\PersonalData;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * MA-312: the customer's data and photos from the custom order form go once the privacy policy no longer keeps them.
 */
class EraseExpiredRequestsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        Storage::fake('local');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * @return array<string, array{CustomOrderStatus}>
     */
    public static function unorderedStatuses(): array
    {
        return [
            'new' => [CustomOrderStatus::New],
            'quoting' => [CustomOrderStatus::Quoting],
            'deposit' => [CustomOrderStatus::Deposit],
            'declined' => [CustomOrderStatus::Declined],
        ];
    }

    #[DataProvider('unorderedStatuses')]
    public function test_an_idea_without_an_order_is_erased_three_months_after_the_last_contact(CustomOrderStatus $status): void
    {
        Carbon::setTestNow('2026-01-15 12:00');
        $idea = $this->ideaWithPhotos(['status' => $status, 'quote' => 45000, 'notes' => 'Zadzwonić']);
        $paths = $this->paths($idea);

        Carbon::setTestNow('2026-04-14 12:00');
        $this->artisan('custom-orders:erase-expired')->assertSuccessful();
        $this->assertSame('Ola', $idea->fresh()->name);
        $this->assertCount(2, $idea->fresh()->getMedia('photos'));

        Carbon::setTestNow('2026-04-16 12:00');
        $this->artisan('custom-orders:erase-expired')->assertSuccessful();

        $idea->refresh();
        $this->assertSame(
            [PersonalData::ERASED_NAME, PersonalData::ERASED_EMAIL, null, null, null, null, '', null],
            [$idea->name, $idea->email, $idea->phone, $idea->quantity, $idea->needed_by, $idea->budget, $idea->description, $idea->notes],
        );
        $this->assertSame([BriefKind::Mugs, $status, 45000], [$idea->kind, $idea->status, $idea->quote]);
        $this->assertTrue($idea->erased_at->equalTo('2026-04-16 12:00'));
        $this->assertSame(ErasureReason::Retention, $idea->erased_because);
        $this->assertCount(0, $idea->getMedia('photos'));
        $this->assertSame(0, Media::query()->count());
        foreach ($paths as $path) {
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_the_last_contact_is_the_latest_save_or_payment_link(): void
    {
        Carbon::setTestNow('2026-01-15 12:00');
        $idea = $this->ideaWithPhotos(['status' => CustomOrderStatus::Quoting]);

        Carbon::setTestNow('2026-02-01 12:00');
        $idea->update(['status' => CustomOrderStatus::Deposit]);

        Carbon::setTestNow('2026-03-01 12:00');
        $idea->paymentLinks()->create(['token' => str_repeat('a', 40), 'amount' => 10000, 'status' => PaymentLinkStatus::Open, 'expires_at' => now()->addDays(14)]);

        Carbon::setTestNow('2026-05-31 12:00');
        app(EraseExpiredRequests::class)();
        $this->assertNull($idea->fresh()->erased_at);

        Carbon::setTestNow('2026-06-02 12:00');
        app(EraseExpiredRequests::class)();
        $this->assertNotNull($idea->fresh()->erased_at);
    }

    public function test_a_shipped_piece_is_kept_two_years_from_handing_over_and_its_payment_stays(): void
    {
        Carbon::setTestNow('2026-01-10 12:00');
        $idea = $this->ideaWithPhotos(['quote' => 90000, 'status' => CustomOrderStatus::Quoting]);
        $this->actingAs(User::factory()->create())
            ->post(route('admin.custom-orders.payment-links.store', $idea), ['part' => 'full'])
            ->assertSessionHasNoErrors();
        $this->post(route('custom-orders.payment.store', PaymentLink::query()->sole()->token), ['payment_method' => 'blik', 'blik_code' => '123456', 'accept_terms' => '1'])
            ->assertRedirect(route('custom-orders.payment.confirmation'));
        $this->assertSame(CustomOrderStatus::InProgress, $idea->fresh()->status);
        $order = Order::query()->sole();

        Carbon::setTestNow('2026-03-01 12:00');
        $idea->fresh()->update(['status' => CustomOrderStatus::Shipped]);
        $this->assertTrue($idea->fresh()->shipped_at->equalTo('2026-03-01 12:00'));

        // 30 days for the delivery, then two years.
        Carbon::setTestNow('2028-03-30 12:00');
        $this->artisan('custom-orders:erase-expired')->assertSuccessful();
        $this->assertNull($idea->fresh()->erased_at);
        $this->assertCount(2, $idea->fresh()->getMedia('photos'));

        Carbon::setTestNow('2028-04-01 12:00');
        $this->artisan('custom-orders:erase-expired')->assertSuccessful();

        $idea->refresh();
        $this->assertSame(PersonalData::ERASED_EMAIL, $idea->email);
        $this->assertCount(0, $idea->getMedia('photos'));
        $this->assertSame([CustomOrderStatus::Shipped, 90000, 90000], [$idea->status, $idea->quote, $idea->paidAmount()]);
        $this->assertSame(
            $order->only(['name', 'email', 'phone', 'total_gross', 'status', 'payment_status']),
            $order->fresh()->only(['name', 'email', 'phone', 'total_gross', 'status', 'payment_status']),
        );
    }

    public function test_a_piece_moved_back_from_shipped_forgets_the_shipping_date(): void
    {
        $idea = $this->idea(['status' => CustomOrderStatus::Shipped]);
        $this->assertNotNull($idea->shipped_at);

        $idea->update(['status' => CustomOrderStatus::InProgress]);
        $this->assertNull($idea->fresh()->shipped_at);

        $idea->update(['notes' => 'Poprawka szkliwa']);
        $this->assertNull($idea->fresh()->shipped_at);
    }

    public function test_a_piece_being_made_is_left_alone(): void
    {
        Carbon::setTestNow('2024-01-15 12:00');
        $idea = $this->ideaWithPhotos(['status' => CustomOrderStatus::InProgress]);

        Carbon::setTestNow('2026-10-31 12:00');
        $this->artisan('custom-orders:erase-expired')->assertSuccessful();

        $this->assertSame('Ola', $idea->fresh()->name);
        $this->assertCount(2, $idea->fresh()->getMedia('photos'));
        $this->assertNull($idea->fresh()->erased_at);
    }

    public function test_the_count_goes_to_the_log_and_the_idea_keeps_its_dates(): void
    {
        Carbon::setTestNow('2026-01-15 12:00');
        $idea = $this->ideaWithPhotos(['status' => CustomOrderStatus::Declined]);
        $this->ideaWithPhotos(['status' => CustomOrderStatus::New]);
        $this->idea(['status' => CustomOrderStatus::New]);

        Log::spy();
        Carbon::setTestNow('2026-05-01 12:00');
        $this->artisan('custom-orders:erase-expired')
            ->expectsOutput('Requests erased: 3, photos deleted: 4, failed: 0.')
            ->assertSuccessful();

        Log::shouldHaveReceived('info')->with('custom-orders:erase-expired erased 3 custom order requests with 4 photos.')->once();
        $this->assertTrue($idea->fresh()->updated_at->equalTo('2026-01-15 12:00'));

        // Nothing left to erase the next night.
        $this->artisan('custom-orders:erase-expired')->expectsOutput('Requests erased: 0, photos deleted: 0, failed: 0.');
    }

    public function test_one_failing_idea_does_not_stop_the_others_and_sends_an_alert(): void
    {
        Carbon::setTestNow('2026-01-15 12:00');
        $broken = $this->ideaWithPhotos(['status' => CustomOrderStatus::Declined]);
        $fine = $this->ideaWithPhotos(['status' => CustomOrderStatus::Declined]);

        Media::deleting(function (Media $media) use ($broken) {
            if ($media->model_id === $broken->id) {
                throw new RuntimeException('Dysk tylko do odczytu');
            }
        });

        $alerts = Mockery::mock(Alerts::class);
        $alerts->shouldReceive('send')->once()->withArgs(fn (string $key, string $subject, string $details) => $key === 'custom-orders-erase-expired'
            && str_contains($details, '#'.$broken->id.': Dysk tylko do odczytu'));
        $this->app->instance(Alerts::class, $alerts);

        Carbon::setTestNow('2026-05-01 12:00');
        $this->artisan('custom-orders:erase-expired')->assertFailed();

        $this->assertNotNull($fine->fresh()->erased_at);
        $this->assertCount(0, $fine->fresh()->getMedia('photos'));
        $this->assertNull($broken->fresh()->erased_at);
    }

    public function test_the_panel_says_when_and_why_the_data_went(): void
    {
        Carbon::setTestNow('2026-01-15 12:00');
        $expired = $this->ideaWithPhotos(['status' => CustomOrderStatus::Declined]);
        $this->ideaWithPhotos(['status' => CustomOrderStatus::Declined, 'email' => 'basia@example.com']);

        Carbon::setTestNow('2026-02-10 12:00');
        app(AnonymizeCustomer::class)('Basia@example.com');

        Carbon::setTestNow('2026-05-01 12:00');
        $this->artisan('custom-orders:erase-expired')->assertSuccessful();

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia-indywidualne?status=declined')
            ->assertOk()
            ->assertSee('Dane i zdjęcia usunięte 1.05.2026 — minął czas przechowywania')
            ->assertSee('Dane i zdjęcia usunięte 10.02.2026 — na prośbę klientki')
            ->assertDontSee('Kubki dla świadków');

        $this->get('/panel/zamowienia-indywidualne/'.$expired->id.'/zdjecia/1')->assertNotFound();
    }

    public function test_it_runs_once_a_night_after_the_backup(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'custom-orders:erase-expired'));

        $this->assertNotNull($event);
        $this->assertSame('30 3 * * *', $event->expression);
    }

    private function ideaWithPhotos(array $attributes = []): CustomOrderRequest
    {
        $idea = $this->idea($attributes);

        foreach ([1, 2] as $number) {
            $idea->addMedia(UploadedFile::fake()->image('kubek.webp', 40, 40))
                ->usingFileName('pomysl-'.$idea->id.'-'.$number.'.webp')
                ->toMediaCollection('photos');
        }

        return $idea->fresh();
    }

    private function idea(array $attributes = []): CustomOrderRequest
    {
        return CustomOrderRequest::query()->create([
            'name' => 'Ola',
            'email' => 'ola@example.com',
            'phone' => '600 100 200',
            'kind' => BriefKind::Mugs,
            'quantity' => '6',
            'needed_by' => 'na wrzesień',
            'budget' => 'ok. 600 zł',
            'description' => 'Kubki dla świadków',
            'status' => CustomOrderStatus::New,
            ...$attributes,
        ]);
    }

    /**
     * @return list<string>
     */
    private function paths(CustomOrderRequest $idea): array
    {
        $paths = $idea->getMedia('photos')->map(fn (Media $media) => $media->getPathRelativeToRoot())->all();

        foreach ($paths as $path) {
            Storage::disk('local')->assertExists($path);
        }

        return $paths;
    }
}
