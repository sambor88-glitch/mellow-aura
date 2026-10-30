<?php

namespace Tests\Feature\Content;

use App\Models\User;
use App\Modules\Content\Models\InstagramPost;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InstagramManualPostsTest extends TestCase
{
    use RefreshDatabase;

    private const FEED = 'https://feeds.behold.so/AbCdEf123';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('public');
        $this->actingAs(User::factory()->create());
    }

    public function test_kasia_adds_a_post_by_hand_and_it_shows_without_a_feed(): void
    {
        $this->post('/panel/tresci/instagram/posty', [
            'photo' => UploadedFile::fake()->image('IMG_2451.jpg', 1800, 2400),
            'permalink' => ' https://www.instagram.com/p/kubek/ ',
            'alt' => 'Kubek w szkliwie lazur na drewnianym stole',
        ])->assertRedirect('/panel/tresci#instagram-reczne')
            ->assertSessionHas('panel_status', 'Post dodany. Widać go na stronie głównej, jeśli sekcja jest włączona.');

        $post = InstagramPost::query()->sole();
        $this->assertTrue($post->is_manual);
        $this->assertSame('https://www.instagram.com/p/kubek/', $post->permalink);
        $this->assertMatchesRegularExpression('#^instagram/manual-[a-z0-9]{10}\.webp$#', $post->image_path);
        Storage::disk('public')->assertExists($post->image_path);
        // Scaled down to 1600 px on the longer side and saved again as WebP.
        $this->assertSame([1200, 1600], array_slice(getimagesize(Storage::disk('public')->path($post->image_path)), 0, 2));

        $this->get('/')->assertOk()
            ->assertSee('Codzienność pracowni')
            ->assertSeeInOrder(['href="https://www.instagram.com/p/kubek/"', 'src="'.Storage::disk('public')->url($post->image_path).'"', 'alt="Kubek w szkliwie lazur na drewnianym stole"'], false);
        $this->get('/panel/tresci')->assertSeeInOrder(['Instagram — posty dodane ręcznie', 'Kubek w szkliwie lazur na drewnianym stole', 'https://www.instagram.com/p/kubek/', 'Nowy post'], false);
    }

    public function test_a_post_needs_a_photo_an_instagram_link_and_a_description(): void
    {
        $this->post('/panel/tresci/instagram/posty', ['permalink' => 'https://example.com/p/1/', 'alt' => ''])
            ->assertRedirect('/panel/tresci#instagram-reczne')
            ->assertSessionHasErrorsIn('instagram-reczne', [
                'photo' => 'Wybierz zdjęcie',
                'permalink' => 'Wklej link do posta, zaczyna się od https://www.instagram.com/',
                'alt' => 'Napisz krótko, co widać na zdjęciu — przeczyta to osoba niewidoma',
            ]);

        $this->post('/panel/tresci/instagram/posty', ['photo' => UploadedFile::fake()->create('cennik.pdf', 10, 'application/pdf'), 'permalink' => 'https://www.instagram.com/p/1/', 'alt' => 'Kubek'])
            ->assertSessionHasErrorsIn('instagram-reczne', ['photo' => 'Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP']);

        $this->assertSame(0, InstagramPost::count());
    }

    public function test_kasias_posts_come_before_the_feeds_in_her_order_and_the_feed_leaves_them_alone(): void
    {
        $this->fakeFeed();
        $this->put('/panel/tresci/instagram', ['instagram_feed_url' => self::FEED]);
        $first = $this->addPost('pierwszy');
        $second = $this->addPost('drugi');

        $this->get('/')->assertSeeInOrder(['/p/pierwszy/', '/p/drugi/', '/p/z-kanalu/'], false);

        $this->post('/panel/tresci/instagram/posty/'.$second->id.'/kolejnosc', ['kierunek' => 'gora'])
            ->assertSessionHas('panel_status', 'Kolejność zmieniona. Klienci już ją widzą.');
        $this->get('/')->assertSeeInOrder(['/p/drugi/', '/p/pierwszy/', '/p/z-kanalu/'], false);

        // Refreshing the feed or clearing its address takes away only the feed's posts.
        $this->artisan('instagram:refresh')->assertSuccessful();
        $this->put('/panel/tresci/instagram', ['instagram_feed_url' => '']);
        $this->assertSame([$first->id, $second->id], InstagramPost::query()->orderBy('id')->pluck('id')->all());
        $this->get('/')->assertSee('/p/pierwszy/', false)->assertDontSee('/p/z-kanalu/', false);
    }

    public function test_only_six_posts_show_and_a_deleted_post_takes_its_photo_with_it(): void
    {
        $posts = collect(range(1, 7))->map(fn (int $number) => $this->addPost('post-'.$number));

        $this->get('/')->assertSee('/p/post-6/', false)->assertDontSee('/p/post-7/', false);

        $this->delete('/panel/tresci/instagram/posty/'.$posts[0]->id)
            ->assertRedirect('/panel/tresci#instagram-reczne')
            ->assertSessionHas('panel_status', 'Post usunięty ze strony');

        $this->assertModelMissing($posts[0]);
        Storage::disk('public')->assertMissing($posts[0]->image_path);
        $this->get('/')->assertDontSee('/p/post-1/', false)->assertSee('/p/post-7/', false);
    }

    public function test_a_feed_post_is_not_moved_or_deleted_by_hand(): void
    {
        $this->fakeFeed();
        $this->put('/panel/tresci/instagram', ['instagram_feed_url' => self::FEED]);
        $post = InstagramPost::query()->sole();

        $this->delete('/panel/tresci/instagram/posty/'.$post->id)->assertNotFound();
        $this->post('/panel/tresci/instagram/posty/'.$post->id.'/kolejnosc', ['kierunek' => 'gora'])->assertNotFound();
        $this->assertModelExists($post);
    }

    public function test_kasia_hides_the_whole_section_and_brings_it_back(): void
    {
        $this->addPost('kubek');

        $this->put('/panel/tresci/instagram', ['home_show_instagram_feed' => '0', 'instagram_feed_url' => ''])
            ->assertSessionHas('panel_status', 'Zapisane. Sekcja z Instagrama jest ukryta na stronie głównej.');

        $this->assertFalse(Setting::find('home_show_instagram_feed')->value);
        $this->get('/')->assertDontSee('Codzienność pracowni')->assertDontSee('/p/kubek/', false);
        $this->get('/panel/tresci')->assertSee('Pokażą się po włączeniu sekcji');

        $this->put('/panel/tresci/instagram', ['home_show_instagram_feed' => '1', 'instagram_feed_url' => '']);
        $this->get('/')->assertSee('Codzienność pracowni')->assertSee('/p/kubek/', false);
    }

    private function addPost(string $slug): InstagramPost
    {
        $this->post('/panel/tresci/instagram/posty', [
            'photo' => UploadedFile::fake()->image($slug.'.jpg', 600, 600),
            'permalink' => 'https://www.instagram.com/p/'.$slug.'/',
            'alt' => 'Zdjęcie '.$slug,
        ])->assertSessionHasNoErrors();

        return InstagramPost::query()->latest('id')->firstOrFail();
    }

    private function fakeFeed(): void
    {
        Http::fake([
            self::FEED => Http::response(['posts' => [[
                'id' => '2001',
                'timestamp' => '2026-09-18T10:00:00+0000',
                'permalink' => 'https://www.instagram.com/p/z-kanalu/',
                'sizes' => ['medium' => ['mediaUrl' => 'https://behold.pictures/abc/2001-medium.webp']],
            ]]]),
            'https://behold.pictures/*' => Http::response('webp', 200, ['Content-Type' => 'image/webp']),
        ]);
    }
}
