<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductGalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();
    }

    public function test_the_main_photo_opens_a_zoom_with_the_gallery_photos(): void
    {
        $product = Product::factory()->create(['slug' => 'kubki']);
        ProductVariant::factory()->create(['product_id' => $product->id]);
        $this->photo($product, 'kubek-cappuccino.webp', 'Kubek z piaskowej gliny z turkusowym wnętrzem');
        $this->photo($product, 'kubek-nie-powinnam.webp', 'Kubek z napisem wbitym stemplem');

        $this->get('/produkt/kubki')
            ->assertOk()
            ->assertSee('Powiększ fakturę')
            ->assertSeeInOrder([
                'aria-label="Powiększone zdjęcie"',
                'alt="Kubek z piaskowej gliny z turkusowym wnętrzem"',
                'alt="Kubek z napisem wbitym stemplem"',
                'aria-label="Zamknij powiększenie"',
                '</dialog>',
            ], false);
    }

    public function test_a_product_without_photos_has_nothing_to_zoom(): void
    {
        $product = Product::factory()->create(['slug' => 'kubki']);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->get('/produkt/kubki')
            ->assertOk()
            ->assertDontSee('Powiększ fakturę')
            ->assertDontSee('Powiększone zdjęcie');
    }

    private function photo(Product $product, string $file, string $alt): void
    {
        $product->addMedia(base_path('zdjecia/'.$file))
            ->preservingOriginal()
            ->withCustomProperties(['alt' => $alt])
            ->toMediaCollection('images');
    }
}
