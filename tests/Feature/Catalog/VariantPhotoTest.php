<?php

namespace Tests\Feature\Catalog;

use App\Models\User;
use App\Modules\Cart\Lines\ProductLine;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

/**
 * A quote mug has one photo per lettering: picking the lettering opens the gallery on its photo,
 * and Google gets that photo for the variant.
 */
class VariantPhotoTest extends TestCase
{
    use RefreshDatabase;

    private Product $mugs;

    /** @var list<Media> */
    private array $photos;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();

        $this->mugs = Product::factory()->create(['slug' => 'kubki-z-cytatem', 'name' => 'Kubki z cytatem', 'is_published' => true, 'stamp_enabled' => true]);
        $this->photos = [
            $this->photo($this->mugs, 'kubek-krolowa-matka.webp', 'Kubek z napisem królowa matka'),
            $this->photo($this->mugs, 'kubek-ochujeje.webp', 'Kubek z napisem ochujeję'),
        ];
    }

    public function test_the_gallery_opens_on_the_photo_of_the_chosen_variant(): void
    {
        $queen = ProductVariant::factory()->create(['product_id' => $this->mugs->id, 'label' => 'Królowa matka', 'stock' => 1, 'media_id' => $this->photos[0]->id]);
        $second = ProductVariant::factory()->create(['product_id' => $this->mugs->id, 'label' => 'Ochujeję', 'stock' => 1, 'media_id' => $this->photos[1]->id]);
        $ownText = ProductVariant::factory()->create(['product_id' => $this->mugs->id, 'label' => 'Twój tekst', 'stock' => null]);

        $this->get('/produkt/kubki-z-cytatem?wariant='.$second->id)
            ->assertOk()
            ->assertSee('x-data="{ active: 1 }"', false)
            // The photo seen first loads first; the other waits.
            ->assertSeeInOrder(['alt="Kubek z napisem królowa matka"', 'loading="lazy"', 'alt="Kubek z napisem ochujeję"', 'fetchpriority="high"'], false);

        $this->get('/produkt/kubki-z-cytatem?wariant='.$queen->id)->assertSee('x-data="{ active: 0 }"', false);
        // "Twój tekst" has no photo of its own and opens on the first one.
        $this->get('/produkt/kubki-z-cytatem?wariant='.$ownText->id)->assertSee('x-data="{ active: 0 }"', false);
    }

    public function test_search_engines_and_google_shopping_get_the_photo_of_each_variant(): void
    {
        ProductVariant::factory()->create(['product_id' => $this->mugs->id, 'label' => 'Królowa matka', 'stock' => 1, 'media_id' => $this->photos[0]->id]);
        $second = ProductVariant::factory()->create(['product_id' => $this->mugs->id, 'label' => 'Ochujeję', 'stock' => 1, 'media_id' => $this->photos[1]->id]);
        ProductVariant::factory()->create(['product_id' => $this->mugs->id, 'label' => 'Twój tekst', 'stock' => null]);
        $this->mugs->update(['show_in_google' => true]);

        $group = $this->structuredData($this->get('/produkt/kubki-z-cytatem')->getContent())->firstWhere('@type', 'ProductGroup');

        $this->assertSame(
            ['kubek-krolowa-matka-card.webp', 'kubek-ochujeje-card.webp', 'kubek-krolowa-matka-card.webp'],
            array_map(fn (array $variant) => basename($variant['image']), $group['hasVariant']),
        );

        $item = null;

        foreach ((new SimpleXMLElement($this->get('/google-merchant.xml')->getContent()))->channel->item as $entry) {
            $fields = $entry->children('http://base.google.com/ns/1.0');
            $item = (string) $fields->id === 'v'.$second->id ? $fields : $item;
        }

        $this->assertNotNull($item);
        $this->assertSame('kubek-ochujeje-card.webp', basename((string) $item->image_link));
        $this->assertSame(['kubek-krolowa-matka-card.webp'], array_map(fn ($link) => basename((string) $link), iterator_to_array($item->additional_image_link, false)));
    }

    public function test_the_basket_shows_the_mug_with_the_chosen_lettering(): void
    {
        $second = ProductVariant::factory()->create(['product_id' => $this->mugs->id, 'label' => 'Ochujeję', 'stock' => 1, 'media_id' => $this->photos[1]->id]);

        $line = new ProductLine('v'.$second->id, 1, $second->load('product'));

        $this->assertSame('Kubek z napisem ochujeję', $line->thumbnailAlt());
        $this->assertStringEndsWith('kubek-ochujeje-thumb.webp', (string) $line->thumbnailUrl());
    }

    public function test_the_panel_sets_the_photo_of_each_size_and_takes_only_this_products_photos(): void
    {
        $queen = ProductVariant::factory()->create(['product_id' => $this->mugs->id, 'label' => 'Królowa matka', 'price_gross' => 7900, 'stock' => 1]);
        $second = ProductVariant::factory()->create(['product_id' => $this->mugs->id, 'label' => 'Ochujeję', 'price_gross' => 7900, 'stock' => 1]);
        $vase = Product::factory()->create();
        $foreign = $this->photo($vase, 'wazon-rzezbiony.webp', 'Wazon');
        $owner = User::factory()->create();

        $this->actingAs($owner)->get('/panel/produkty')
            ->assertOk()
            ->assertSee('aria-label="Zdjęcie, rozmiar 1"', false)
            ->assertSee('„Zdjęcie” to fotka, którą karta produktu pokaże po wybraniu tego rozmiaru');

        $this->actingAs($owner)
            ->put('/panel/produkty/'.$this->mugs->id, [
                'form' => 'produkt-'.$this->mugs->id,
                'name' => 'Kubki z cytatem',
                'category_id' => $this->mugs->category_id,
                'is_published' => '1',
                'variants' => [
                    ['id' => $queen->id, 'label' => 'Królowa matka', 'price' => '79', 'stock' => '1', 'media_id' => (string) $this->photos[0]->id],
                    ['id' => $second->id, 'label' => 'Ochujeję', 'price' => '79', 'stock' => '1', 'media_id' => (string) $foreign->id],
                ],
            ])
            ->assertRedirect('/panel/produkty#produkt-'.$this->mugs->id);

        $this->assertSame([$this->photos[0]->id, null], $this->mugs->variants()->orderBy('id')->pluck('media_id')->all());
        $this->actingAs($owner)->get('/panel/produkty')
            ->assertSee('<option value="'.$this->photos[0]->id.'" selected>zdjęcie 1</option>', false);

        // A deleted photo leaves its size on the first one.
        $this->photos[0]->delete();
        $this->assertSame([null, null], $this->mugs->variants()->orderBy('id')->pluck('media_id')->all());
    }

    private function photo(Product $product, string $file, string $alt): Media
    {
        return $product->addMedia(base_path('zdjecia/'.$file))
            ->preservingOriginal()
            ->withCustomProperties(['alt' => $alt])
            ->toMediaCollection('images');
    }
}
