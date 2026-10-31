<?php

namespace Tests\Feature\CustomOrders;

use App\Models\User;
use App\Modules\CustomOrders\Actions\PrunePhotos;
use App\Modules\CustomOrders\Enums\BriefKind;
use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\CustomOrders\Enums\PaymentLinkStatus;
use App\Modules\CustomOrders\Models\CustomOrderRequest;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * MA-312: the photos from the custom order form go once the privacy policy no longer keeps them.
 */
class PrunePhotosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    /**
     * @dataProvider unorderedStatuses
     */
    public function test_an_idea_without_an_order_keeps_its_photos_three_months_from_the_last_contact(CustomOrderStatus $status): void
    {
        Carbon::setTestNow('2026-01-15 12:00');
        $idea = $this->ideaWithPhotos(['status' => $status]);
        $paths = $this->paths($idea);

        Carbon::setTestNow('2026-04-14 12:00');
        $this->artisan('custom-orders:prune-photos')->assertSuccessful();
        $this->assertCount(2, $idea->fresh()->getMedia('photos'));

        Carbon::setTestNow('2026-04-16 12:00');
        $this->artisan('custom-orders:prune-photos')->assertSuccessful();

        $idea->refresh();
        $this->assertCount(0, $idea->getMedia('photos'));
        $this->assertSame(0, Media::query()->count());
        $this->assertTrue($idea->photos_deleted_at->equalTo('2026-04-16 12:00'));
        $this->assertSame($status, $idea->status);
        $this->assertSame('Kubki dla świadków', $idea->description);
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
        app(PrunePhotos::class)();
        $this->assertCount(2, $idea->fresh()->getMedia('photos'));

        Carbon::setTestNow('2026-06-02 12:00');
        app(PrunePhotos::class)();
        $this->assertCount(0, $idea->fresh()->getMedia('photos'));
    }

    public function test_a_shipped_piece_keeps_its_photos_two_years_from_handing_over(): void
    {
        Carbon::setTestNow('2026-01-10 12:00');
        $idea = $this->ideaWithPhotos(['status' => CustomOrderStatus::InProgress]);

        Carbon::setTestNow('2026-03-01 12:00');
        $idea->update(['status' => CustomOrderStatus::Shipped]);
        $this->assertTrue($idea->fresh()->shipped_at->equalTo('2026-03-01 12:00'));

        // 30 days for the delivery, then two years.
        Carbon::setTestNow('2028-03-30 12:00');
        $this->artisan('custom-orders:prune-photos')->assertSuccessful();
        $this->assertCount(2, $idea->fresh()->getMedia('photos'));

        Carbon::setTestNow('2028-04-01 12:00');
        $this->artisan('custom-orders:prune-photos')->assertSuccessful();
        $this->assertCount(0, $idea->fresh()->getMedia('photos'));
        $this->assertSame(CustomOrderStatus::Shipped, $idea->fresh()->status);
    }

    public function test_a_piece_moved_back_from_shipped_forgets_the_shipping_date(): void
    {
        $idea = $this->ideaWithPhotos(['status' => CustomOrderStatus::Shipped]);
        $this->assertNotNull($idea->shipped_at);

        $idea->update(['status' => CustomOrderStatus::InProgress]);
        $this->assertNull($idea->fresh()->shipped_at);

        $idea->update(['notes' => 'Poprawka szkliwa']);
        $this->assertNull($idea->fresh()->shipped_at);
    }

    public function test_a_piece_being_made_keeps_its_photos(): void
    {
        Carbon::setTestNow('2024-01-15 12:00');
        $idea = $this->ideaWithPhotos(['status' => CustomOrderStatus::InProgress]);

        Carbon::setTestNow('2026-10-31 12:00');
        $this->artisan('custom-orders:prune-photos')->assertSuccessful();

        $this->assertCount(2, $idea->fresh()->getMedia('photos'));
        $this->assertNull($idea->fresh()->photos_deleted_at);
    }

    public function test_the_count_goes_to_the_log_and_the_idea_keeps_its_dates(): void
    {
        Carbon::setTestNow('2026-01-15 12:00');
        $idea = $this->ideaWithPhotos(['status' => CustomOrderStatus::Declined]);
        $this->ideaWithPhotos(['status' => CustomOrderStatus::New]);
        $this->idea(['status' => CustomOrderStatus::New]);

        Log::spy();
        Carbon::setTestNow('2026-05-01 12:00');
        $this->artisan('custom-orders:prune-photos')
            ->expectsOutput('Photos deleted: 4, requests: 2, failed: 0.')
            ->assertSuccessful();

        Log::shouldHaveReceived('info')->with('custom-orders:prune-photos deleted 4 photos of 2 custom order requests.')->once();
        $this->assertTrue($idea->fresh()->updated_at->equalTo('2026-01-15 12:00'));
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
        $alerts->shouldReceive('send')->once()->withArgs(fn (string $key, string $subject, string $details) => $key === 'custom-orders-prune-photos'
            && str_contains($details, '#'.$broken->id.': Dysk tylko do odczytu'));
        $this->app->instance(Alerts::class, $alerts);

        Carbon::setTestNow('2026-05-01 12:00');
        $this->artisan('custom-orders:prune-photos')->assertFailed();

        $this->assertCount(0, $fine->fresh()->getMedia('photos'));
        $this->assertNull($broken->fresh()->photos_deleted_at);
    }

    public function test_the_panel_says_when_the_photos_were_deleted(): void
    {
        Carbon::setTestNow('2026-01-15 12:00');
        $idea = $this->ideaWithPhotos(['status' => CustomOrderStatus::Declined]);

        Carbon::setTestNow('2026-05-01 12:00');
        $this->artisan('custom-orders:prune-photos')->assertSuccessful();

        $this->actingAs(User::factory()->create())
            ->get('/panel/zamowienia-indywidualne?status=declined')
            ->assertOk()
            ->assertSee('Zdjęcia usunięte 1.05.2026')
            ->assertDontSee('Zdjęcia od Ola');

        $this->get('/panel/zamowienia-indywidualne/'.$idea->id.'/zdjecia/1')->assertNotFound();
    }

    public function test_it_runs_once_a_night(): void
    {
        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'custom-orders:prune-photos'));

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
            'kind' => BriefKind::Mugs,
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
