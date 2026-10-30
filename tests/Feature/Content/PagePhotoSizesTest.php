<?php

namespace Tests\Feature\Content;

use App\Modules\Content\Support\PagePhoto;
use App\Modules\Content\Support\PagePhotos;
use App\Modules\Content\Support\PagePhotoSizes;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PagePhotoSizesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        Storage::fake('public');
    }

    public function test_a_photo_from_zdjecia_gets_a_smaller_copy_made_once(): void
    {
        // wazon-rzezbiony.webp is 904 px wide: copies of 400 and 800 px, the photo itself as the largest.
        $photo = app(PagePhotos::class)->get('home-float-5');
        $srcset = $photo->srcset();
        $copies = Storage::disk('public')->files(PagePhotoSizes::DIRECTORY);

        $this->assertCount(2, $copies);
        $this->assertSame([400, 800], array_map(fn (string $copy) => getimagesize(Storage::disk('public')->path($copy))[0], $copies));
        $this->assertSame(Storage::disk('public')->url($copies[0]).' 400w, '.Storage::disk('public')->url($copies[1]).' 800w, '.$photo->url.' 904w', $srcset);
        $this->assertSame('srcset="'.e($srcset).'" sizes="100px"', (string) $photo->srcsetAttributes('100px'));

        $photo->srcset();
        $this->assertSame($copies, Storage::disk('public')->files(PagePhotoSizes::DIRECTORY));
    }

    public function test_the_home_page_offers_the_copies_and_a_missing_photo_offers_none(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('sizes="(max-aspect-ratio: 3/4) 75vh, 100vw"', false)
            // The hero photo is asked for in the <head>, before the stylesheet (MA-271).
            ->assertSee('imagesizes="(max-aspect-ratio: 3/4) 75vh, 100vw"', false)
            ->assertSee('sizes="(max-width: 760px) 100px, 13vw"', false);

        $this->assertNull(app(PagePhotoSizes::class)->srcset('page-photos/nie-ma-takiego.webp', 'https://example.test/x.webp'));
        $photo = new PagePhoto('contact', null, null, ['x' => 50, 'y' => 50], null, null, false, true);
        $this->assertNull($photo->srcset());
        $this->assertSame('', (string) $photo->srcsetAttributes('100vw'));
    }
}
