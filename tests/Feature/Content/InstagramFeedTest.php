<?php

namespace Tests\Feature\Content;

use App\Models\User;
use App\Modules\Content\Models\InstagramPost;
use App\Modules\Settings\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstagramFeedTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = 'https://feeds.behold.so/AbCdEf123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        $this->travelTo(now()->setDate(2026, 9, 20)->setTime(12, 0));
        Setting::create(['key' => 'instagram_handle', 'value' => '@mellowaura.ceramika']);
    }

    public function test_saving_the_feed_fetches_the_newest_photos_onto_the_shops_own_disk(): void
    {
        $this->fakeFeed();

        $this->actingAs(User::factory()->create())
            ->put('/panel/tresci/instagram', ['instagram_feed_url' => ' '.self::FEED.' ', 'instagram_feed_hashtag' => ''])
            ->assertRedirect('/panel/tresci#instagram')
            ->assertSessionHas('panel_status', 'Pobrane zdjęcia: 2. Są już na stronie głównej.');

        $this->assertSame(self::FEED, Setting::find('instagram_feed_url')->value);
        $this->assertSame(['1002', '1001'], InstagramPost::query()->latest('posted_at')->pluck('external_id')->all());
        Storage::disk('public')->assertExists(['instagram/1001.webp', 'instagram/1002.jpg']);
        // The post linking outside Instagram and the photo from another host are left out.
        Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'example.com'));

        $this->get('/')
            ->assertOk()
            ->assertSeeInOrder(['@mellowaura.ceramika', 'Codzienność pracowni', 'href="https://www.instagram.com/mellowaura.ceramika/"', 'prosto z Instagrama', 'ostatni post 1 dzień temu'], false)
            ->assertSeeInOrder(['href="https://www.instagram.com/p/second/"', 'src="'.Storage::disk('public')->url('instagram/1002.jpg').'"', 'alt="Kubki schną na parapecie"', 'href="https://www.instagram.com/p/first/"', 'alt="Szkliwo prosto z pieca."'], false)
            ->assertDontSee('cdninstagram')
            ->assertDontSee('behold.pictures');

        $this->get('/panel/tresci')->assertSeeInOrder(['Instagram na stronie głównej', 'value="'.self::FEED.'"', 'na stronie teraz'], false);
    }

    public function test_a_hashtag_keeps_only_the_posts_that_carry_it(): void
    {
        $this->fakeFeed();

        $this->actingAs(User::factory()->create())
            ->put('/panel/tresci/instagram', ['instagram_feed_url' => self::FEED, 'instagram_feed_hashtag' => 'ZPracowni'])
            ->assertSessionHas('panel_status', 'Pobrane zdjęcia: 1. Są już na stronie głównej.');

        $this->assertSame('#ZPracowni', Setting::find('instagram_feed_hashtag')->value);
        $this->assertSame(['1001'], InstagramPost::query()->pluck('external_id')->all());
    }

    public function test_only_a_behold_address_is_accepted(): void
    {
        Http::fake();

        $this->actingAs(User::factory()->create())
            ->put('/panel/tresci/instagram', ['instagram_feed_url' => 'http://127.0.0.1/feed', 'instagram_feed_hashtag' => '#dwa słowa'])
            ->assertSessionHasErrorsIn('instagram', [
                'instagram_feed_url' => 'Wklej adres kanału JSON z Behold — zaczyna się od https://feeds.behold.so/',
                'instagram_feed_hashtag' => 'Wpisz jeden hashtag bez spacji, np. #zpracowni',
            ]);

        Http::assertNothingSent();
    }

    public function test_a_feed_that_fails_keeps_the_photos_already_on_the_page(): void
    {
        $this->fakeFeed();
        $owner = User::factory()->create();
        $this->actingAs($owner)->put('/panel/tresci/instagram', ['instagram_feed_url' => self::FEED]);

        // Stubs added to a faked client stack up, so the failing feed gets a fresh client.
        Http::swap(new Factory);
        Http::fake([self::FEED => Http::response('Not found', 404)]);
        $this->put('/panel/tresci/instagram', ['instagram_feed_url' => self::FEED])
            ->assertSessionHas('panel_status', 'Zapisane, ale zdjęć nie udało się pobrać: Pod tym adresem nie ma kanału z Behold — skopiuj adres JSON jeszcze raz.');
        $this->assertSame(2, InstagramPost::count());

        $this->artisan('instagram:refresh')
            ->expectsOutput('Pod tym adresem nie ma kanału z Behold — skopiuj adres JSON jeszcze raz')
            ->assertFailed();
    }

    public function test_clearing_the_address_takes_the_section_and_the_photos_away(): void
    {
        $this->fakeFeed();
        $this->actingAs(User::factory()->create())->put('/panel/tresci/instagram', ['instagram_feed_url' => self::FEED]);

        $this->put('/panel/tresci/instagram', ['instagram_feed_url' => ''])
            ->assertSessionHas('panel_status', 'Sekcja z Instagrama zdjęta ze strony głównej');

        $this->assertSame(0, InstagramPost::count());
        Storage::disk('public')->assertMissing('instagram/1001.webp');
        $this->get('/')->assertDontSee('Codzienność pracowni');
    }

    public function test_the_scheduler_refreshes_the_feed_every_hour(): void
    {
        $this->fakeFeed();
        Setting::create(['key' => 'instagram_feed_url', 'value' => self::FEED]);

        $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'instagram:refresh'));

        $this->assertNotNull($event);
        $this->assertSame('0 * * * *', $event->expression);
        $this->artisan('instagram:refresh')->expectsOutput('Zdjęć na stronie głównej: 2')->assertSuccessful();
    }

    private function fakeFeed(): void
    {
        $post = fn (string $id, string $when, string $permalink, string $image, array $extra = []) => [
            'id' => $id,
            'timestamp' => $when,
            'permalink' => $permalink,
            'mediaType' => 'IMAGE',
            'mediaUrl' => 'https://scontent.cdninstagram.com/'.$id.'.jpg',
            'sizes' => ['medium' => ['mediaUrl' => $image, 'width' => 700, 'height' => 700]],
            ...$extra,
        ];

        Http::fake([
            self::FEED => Http::response(['username' => 'mellowaura.ceramika', 'posts' => [
                $post('1001', '2026-09-18T10:00:00+0000', 'https://www.instagram.com/p/first/', 'https://behold.pictures/abc/1001-medium.webp', ['caption' => 'Szkliwo prosto z pieca. #zpracowni #ceramika', 'hashtags' => ['zpracowni', 'ceramika']]),
                $post('1002', '2026-09-19T09:00:00+0000', 'https://www.instagram.com/p/second/', 'https://img.behold.pictures/abc/1002-medium.jpg', ['caption' => 'Kubki', 'altText' => 'Kubki schną na parapecie', 'hashtags' => []]),
                $post('1003', '2026-09-19T11:00:00+0000', 'https://example.com/p/third/', 'https://behold.pictures/abc/1003-medium.jpg'),
                $post('1004', '2026-09-17T11:00:00+0000', 'https://www.instagram.com/p/fourth/', 'https://example.com/1004.jpg'),
            ]]),
            'https://behold.pictures/*' => Http::response('webp', 200, ['Content-Type' => 'image/webp']),
            'https://img.behold.pictures/*' => Http::response('jpeg', 200, ['Content-Type' => 'image/jpeg; charset=binary']),
        ]);
    }
}
