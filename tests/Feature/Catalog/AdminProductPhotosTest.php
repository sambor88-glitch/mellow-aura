<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

class AdminProductPhotosTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();
        $this->owner = User::factory()->create();
        $this->product = Product::factory()->create(['name' => 'Miska z paprocią', 'slug' => 'miska-z-paprocia']);
        $this->variant = ProductVariant::factory()->create(['product_id' => $this->product->id]);
    }

    public function test_photos_are_scaled_down_saved_as_webp_and_get_thumbnails(): void
    {
        $this->actingAs($this->owner)
            ->post($this->photosUrl(), ['photos' => [
                UploadedFile::fake()->image('IMG_4821.jpg', 3200, 2400),
                UploadedFile::fake()->image('IMG_4822.png', 800, 1000),
            ]])
            ->assertRedirect('/panel/produkty#produkt-'.$this->product->id)
            ->assertSessionHas('panel_status', '2 zdjęcia dodane');

        [$first, $second] = $this->product->fresh()->getMedia('images')->all();

        $this->assertStringStartsWith('miska-z-paprocia-', $first->file_name);
        $this->assertSame('image/webp', $first->mime_type);
        $this->assertSame([2400, 1800], array_slice(getimagesize($first->getPath()), 0, 2));
        $this->assertSame([800, 1000], array_slice(getimagesize($second->getPath()), 0, 2));
        $this->assertSame([300, 375], array_slice(getimagesize($first->getPath('thumb')), 0, 2));
        $this->assertTrue($first->hasGeneratedConversion('card'));
        // No description yet: the shop describes the photo with the product name.
        $this->assertNull($first->getCustomProperty('alt'));

        $this->get('/panel/produkty')->assertOk()->assertSee('2 zdjęcia')->assertSee($first->getUrl('thumb'), false);
    }

    public function test_a_phone_photo_is_turned_upright_once_and_loses_its_exif(): void
    {
        $this->actingAs($this->owner)->post($this->photosUrl(), ['photos' => [$this->phonePhoto(400, 300)]]);

        $path = $this->product->fresh()->getFirstMedia('images')->getPath();

        $this->assertSame([300, 400], array_slice(getimagesize($path), 0, 2));
        $this->assertDoesNotMatchRegularExpression('/exif/i', (string) file_get_contents($path));
    }

    public function test_a_photo_can_be_moved_and_deleted(): void
    {
        $this->actingAs($this->owner)->post($this->photosUrl(), ['photos' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ]]);
        [$first, $second] = $this->product->fresh()->getMedia('images')->all();

        $this->post($this->photosUrl().'/'.$second->id.'/przesun', ['kierunek' => 'lewo'])
            ->assertRedirect('/panel/produkty#produkt-'.$this->product->id);
        $this->assertSame([$second->id, $first->id], $this->product->fresh()->getMedia('images')->pluck('id')->all());

        $path = $first->getPath();
        $this->delete($this->photosUrl().'/'.$first->id)->assertSessionHas('panel_status', 'Zdjęcie usunięte');

        $this->assertNull(Media::find($first->id));
        $this->assertFileDoesNotExist($path);
    }

    public function test_only_photos_are_accepted_and_only_for_their_own_product(): void
    {
        $other = Product::factory()->create();
        $pdf = fn () => ['photos' => [UploadedFile::fake()->create('cennik.pdf', 100, 'application/pdf')]];
        $message = 'Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP';

        $this->actingAs($this->owner)->followingRedirects()->post($this->photosUrl(), $pdf())->assertSee($message);
        $this->post($this->photosUrl(), $pdf())->assertSessionHasErrorsIn('zdjecia-'.$this->product->id, ['photos.0' => $message]);

        $this->post($this->photosUrl(), ['photos' => [UploadedFile::fake()->image('a.jpg')]]);
        $photo = $this->product->fresh()->getFirstMedia('images');

        $this->delete('/panel/produkty/'.$other->id.'/zdjecia/'.$photo->id)->assertNotFound();
        $this->assertNotNull(Media::find($photo->id));
    }

    public function test_photo_descriptions_are_saved_with_the_product_form(): void
    {
        $this->actingAs($this->owner)->post($this->photosUrl(), ['photos' => [
            UploadedFile::fake()->image('a.jpg'),
            UploadedFile::fake()->image('b.jpg'),
        ]]);
        [$first, $second] = $this->product->fresh()->getMedia('images')->all();
        $second->setCustomProperty('alt', 'Stary opis')->save();

        $this->get('/panel/produkty')->assertSee('Opisy zdjęć')->assertSee('name="photo_alts['.$first->id.']"', false);

        $this->put('/panel/produkty/'.$this->product->id, [
            'form' => 'produkt-'.$this->product->id,
            'name' => 'Miska z paprocią',
            'category_id' => $this->product->category_id,
            'variants' => [['id' => $this->variant->id, 'label' => '', 'price' => '120', 'stock' => '2']],
            'photo_alts' => [$first->id => 'Miska od spodu, widać odcisk paproci', $second->id => ''],
        ])->assertSessionHasNoErrors();

        $this->assertSame('Miska od spodu, widać odcisk paproci', $first->fresh()->getCustomProperty('alt'));
        $this->assertFalse($second->fresh()->hasCustomProperty('alt'));
    }

    public function test_a_new_product_can_come_with_photos_and_the_shop_shows_the_smaller_copy(): void
    {
        $category = Category::factory()->create();

        $this->actingAs($this->owner)
            ->post('/panel/produkty', [
                'form' => 'nowy-produkt',
                'name' => 'Wazon rzeźbiony',
                'category_id' => $category->id,
                'is_published' => '1',
                'variants' => [['label' => '', 'price' => '249', 'stock' => '1']],
                'photos' => [UploadedFile::fake()->image('wazon.jpg', 1600, 2000)],
            ])
            ->assertRedirect('/panel/produkty');

        $cover = Product::where('slug', 'wazon-rzezbiony')->firstOrFail()->getFirstMedia('images');

        $this->get('/sklep')->assertSee($cover->getUrl('card'), false);
    }

    private function photosUrl(): string
    {
        return '/panel/produkty/'.$this->product->id.'/zdjecia';
    }

    /**
     * A JPEG the way a phone saves it: pixels on their side and EXIF Orientation 6 saying how to turn them.
     */
    private function phonePhoto(int $width, int $height): UploadedFile
    {
        ob_start();
        imagejpeg(imagecreatetruecolor($width, $height));
        $jpeg = (string) ob_get_clean();

        $exif = "Exif\0\0".'MM'.pack('nN', 42, 8).pack('n', 1).pack('nnNnn', 0x0112, 3, 1, 6, 0).pack('N', 0);

        return UploadedFile::fake()->createWithContent('IMG_4821.jpg', "\xFF\xD8\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2));
    }
}
