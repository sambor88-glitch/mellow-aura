<?php

namespace Tests\Feature\Shared;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Enums\GoogleCategory;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Gifts\Models\Bundle;
use App\Modules\Gifts\Models\BundleItem;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use Tests\TestCase;

class CatalogFeedTest extends TestCase
{
    use RefreshDatabase;

    private const REQUIRED = ['id', 'title', 'description', 'link', 'image_link', 'availability', 'price', 'brand', 'condition'];

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();
    }

    public function test_every_size_on_the_shelf_goes_to_meta_and_pinterest_with_the_fields_both_require(): void
    {
        $this->deliveryOptions();
        $vases = Category::factory()->create(['name' => 'Wazony i patery', 'group' => CategoryGroup::Ceramics]);
        $vase = $this->product(['slug' => 'wazony', 'name' => 'Wazony', 'category_id' => $vases->id, 'description' => "Wazon <b>lepiony</b>\n\nz ręki.", 'google_category' => GoogleCategory::Vases]);
        $low = ProductVariant::factory()->create(['product_id' => $vase->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'compare_at_price' => 27900, 'stock' => 3]);
        $mid = ProductVariant::factory()->create(['product_id' => $vase->id, 'label' => 'Średni 20 cm', 'price_gross' => 32000, 'stock' => 1]);

        $response = $this->get('/social-catalog.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringStartsWith('noindex', (string) $response->headers->get('X-Robots-Tag'));

        $xml = new SimpleXMLElement($response->getContent());
        $this->assertSame('2.0', (string) $xml['version']);
        $items = $this->items($response->getContent());
        $this->assertSame(['v'.$low->id, 'v'.$mid->id], array_keys($items));

        foreach ($items as $item) {
            foreach (self::REQUIRED as $field) {
                $this->assertNotSame('', (string) $item->{$field}, $field.' is empty');
            }
        }

        $first = $items['v'.$low->id];
        $this->assertSame('Wazony — Niski 16 cm', (string) $first->title);
        $this->assertSame('Wazon lepiony z ręki.', (string) $first->description);
        $this->assertSame(url('/produkt/wazony').'?wariant='.$low->id, (string) $first->link);
        $this->assertStringContainsString('/storage/', (string) $first->image_link);
        $this->assertSame('in stock', (string) $first->availability);
        $this->assertSame('279.00 PLN', (string) $first->price);
        $this->assertSame('239.00 PLN', (string) $first->sale_price);
        $this->assertSame('MellowAura', (string) $first->brand);
        $this->assertSame('new', (string) $first->condition);
        $this->assertSame('602', (string) $first->google_product_category);
        $this->assertSame('Ceramika > Wazony i patery', (string) $first->product_type);
        // Both sizes are one product with options.
        $this->assertSame('p'.$vase->id, (string) $first->item_group_id);
        $this->assertSame('p'.$vase->id, (string) $items['v'.$mid->id]->item_group_id);
        // Merchant Center's own fields stay in Google's file.
        $this->assertCount(0, $first->return_policy_label);
        $this->assertCount(0, $first->identifier_exists);

        $this->assertCount(2, $first->shipping);
        $this->assertSame(['PL', 'InPost Paczkomat', '16.00 PLN'], $this->shipping($first->shipping[0]));
        $this->assertSame(['PL', 'Kurier InPost', '22.00 PLN'], $this->shipping($first->shipping[1]));
    }

    public function test_a_size_off_the_shelf_and_a_product_without_a_photo_stay_out(): void
    {
        $category = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $shown = $this->product(['category_id' => $category->id]);
        $onShelf = ProductVariant::factory()->create(['product_id' => $shown->id, 'stock' => 2]);
        $soldOut = ProductVariant::factory()->create(['product_id' => $shown->id, 'stock' => 0]);
        $withoutPhoto = Product::factory()->create(['category_id' => $category->id]);
        ProductVariant::factory()->create(['product_id' => $withoutPhoto->id, 'stock' => 2]);
        $hidden = $this->product(['category_id' => $category->id, 'is_published' => false]);
        ProductVariant::factory()->create(['product_id' => $hidden->id, 'stock' => 2]);

        $this->assertSame(['v'.$onShelf->id], array_keys($this->items($this->get('/social-catalog.xml')->getContent())));
        // Google still gets the sold-out size, marked as such.
        $this->assertArrayHasKey('v'.$soldOut->id, $this->items($this->get('/google-merchant.xml')->getContent()));
    }

    public function test_a_gift_set_goes_without_a_group(): void
    {
        $this->deliveryOptions();
        $category = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $mug = ProductVariant::factory()->create(['product_id' => $this->product(['name' => 'Kubek', 'category_id' => $category->id])->id, 'price_gross' => 9000, 'stock' => 3]);
        $set = Bundle::factory()->create(['name' => 'Kubek w pudełku', 'discount_percent' => 10, 'is_published' => true]);
        BundleItem::factory()->create(['bundle_id' => $set->id, 'product_variant_id' => $mug->id, 'sort_order' => 1]);

        $item = $this->items($this->get('/social-catalog.xml')->getContent())['bundle-'.$set->id];

        $this->assertSame('in stock', (string) $item->availability);
        $this->assertCount(0, $item->item_group_id);
        $this->assertCount(0, $item->is_bundle);
    }

    public function test_the_feed_is_open_on_staging_for_meta_and_pinterest(): void
    {
        config(['staging.user' => 'mellowaura', 'staging.password' => 'secret']);

        $this->get('/social-catalog.xml')->assertOk();
        $this->get('/google-merchant.xml')->assertUnauthorized();
    }

    public function test_the_product_page_gives_pinterest_and_meta_the_price_and_availability(): void
    {
        $product = $this->product(['slug' => 'wazony', 'category_id' => Category::factory()->create(['group' => CategoryGroup::Ceramics])->id]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => 23900, 'stock' => 3]);

        $this->get('/produkt/wazony')
            ->assertOk()
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta property="product:price:amount" content="239.00">', false)
            ->assertSee('<meta property="product:price:currency" content="PLN">', false)
            ->assertSee('<meta property="product:availability" content="in stock">', false);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function product(array $attributes): Product
    {
        $product = Product::factory()->create($attributes);
        $product->addMedia(base_path('zdjecia/talerz-niebieski-odcisk.webp'))->preservingOriginal()->toMediaCollection('images');

        return $product;
    }

    private function deliveryOptions(): void
    {
        Setting::create(['key' => 'free_shipping_threshold', 'value' => 40000]);
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'note' => '1–2 dni robocze', 'price_gross' => 1600],
            ['code' => 'courier', 'label' => 'Kurier InPost', 'note' => 'Do rąk, 1 dzień', 'price_gross' => 2200],
            ['code' => 'studio_pickup', 'label' => 'Odbiór w pracowni', 'note' => 'Kraków, po umówieniu', 'price_gross' => 0],
        ]]);
    }

    /**
     * @return array<string, SimpleXMLElement>
     */
    private function items(string $xml): array
    {
        $items = [];

        foreach ((new SimpleXMLElement($xml))->channel->item as $item) {
            $fields = $item->children('http://base.google.com/ns/1.0');
            $items[(string) $fields->id] = $fields;
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function shipping(SimpleXMLElement $shipping): array
    {
        return array_values(array_map('strval', iterator_to_array($shipping->children('http://base.google.com/ns/1.0'), false)));
    }
}
