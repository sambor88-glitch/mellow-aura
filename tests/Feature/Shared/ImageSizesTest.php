<?php

namespace Tests\Feature\Shared;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Photos reserve their frame before they load, so nothing jumps under the reader's thumb.
 *
 * Two frames have no shape to declare and stay out of this list: the photo above the studio takes its
 * height from the screen, and the zoomed photo fills whatever is left of it.
 */
class ImageSizesTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_photo_on_a_public_page_has_a_width_and_a_height(): void
    {
        Storage::fake('public');
        $this->withoutVite();
        $product = Product::factory()->create(['slug' => 'kubki', 'name' => 'Kubki']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 3]);
        $product->addMedia(base_path('zdjecia/kubek-cappuccino.webp'))
            ->preservingOriginal()
            ->toMediaCollection('images');

        foreach (['/sklep', '/o-mnie', '/kontakt', '/zamowienia-indywidualne'] as $url) {
            $html = $this->get($url)->assertOk()->getContent();
            preg_match_all('/<img\b[^>]*>/', $html, $tags);

            $this->assertNotEmpty($tags[0], "Na stronie {$url} nie ma zdjęć do sprawdzenia");

            foreach ($tags[0] as $tag) {
                $this->assertMatchesRegularExpression('/width="\d+"\s+height="\d+"/', $tag, "Zdjęcie bez wymiarów na {$url}: {$tag}");
            }
        }
    }
}
