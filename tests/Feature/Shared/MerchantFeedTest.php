<?php

namespace Tests\Feature\Shared;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Enums\GoogleCategory;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Gifts\Models\Bundle;
use App\Modules\Gifts\Models\BundleItem;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use SimpleXMLElement;
use Tests\TestCase;

class MerchantFeedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->withoutVite();
    }

    public function test_every_size_of_a_published_product_with_a_photo_goes_to_google_with_its_price_stock_and_delivery(): void
    {
        $this->deliveryOptions();
        $vases = Category::factory()->create(['name' => 'Wazony i patery', 'group' => CategoryGroup::Ceramics]);
        $vase = $this->product(['slug' => 'wazony', 'name' => 'Wazony', 'category_id' => $vases->id, 'description' => "Wazon <b>lepiony</b>\n\nz ręki.", 'google_category' => GoogleCategory::Vases]);
        $low = ProductVariant::factory()->create(['product_id' => $vase->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'compare_at_price' => 27900, 'stock' => 3]);
        $tall = ProductVariant::factory()->create(['product_id' => $vase->id, 'label' => 'Wysoki 24 cm', 'price_gross' => 45000, 'stock' => 0]);

        $response = $this->get('/google-merchant.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $this->assertStringStartsWith('noindex', (string) $response->headers->get('X-Robots-Tag'));

        $items = $this->items($response->getContent());
        $this->assertSame(['v'.$low->id, 'v'.$tall->id], array_keys($items));

        $first = $items['v'.$low->id];
        $this->assertSame('Wazony — Niski 16 cm', (string) $first->title);
        $this->assertSame('Wazon lepiony z ręki.', (string) $first->description);
        $this->assertSame(url('/produkt/wazony').'?wariant='.$low->id, (string) $first->link);
        $this->assertStringContainsString('/storage/', (string) $first->image_link);
        $this->assertSame('in_stock', (string) $first->availability);
        // The crossed-out price from the panel is the regular price, the current one the sale price.
        $this->assertSame('279.00 PLN', (string) $first->price);
        $this->assertSame('239.00 PLN', (string) $first->sale_price);
        $this->assertSame('MellowAura', (string) $first->brand);
        $this->assertSame('no', (string) $first->identifier_exists);
        $this->assertSame('new', (string) $first->condition);
        $this->assertSame('602', (string) $first->google_product_category);
        $this->assertSame('Ceramika > Wazony i patery', (string) $first->product_type);
        $this->assertCount(0, $first->return_policy_label);

        // Pickup at the studio is no delivery; times come from the panel and the delivery option's note.
        $this->assertCount(2, $first->shipping);
        $this->assertSame(['PL', 'InPost Paczkomat', '16.00 PLN', '3', '5', '1', '2'], $this->shipping($first->shipping[0]));
        $this->assertSame(['PL', 'Kurier InPost', '22.00 PLN', '3', '5', '1', '1'], $this->shipping($first->shipping[1]));

        $second = $items['v'.$tall->id];
        $this->assertSame('out_of_stock', (string) $second->availability);
        $this->assertSame('450.00 PLN', (string) $second->price);
        $this->assertCount(0, $second->sale_price);
        // From the free delivery threshold every option costs nothing.
        $this->assertSame('0.00 PLN', $this->shipping($second->shipping[0])[2]);
    }

    public function test_a_product_kasia_keeps_out_of_google_hidden_or_without_a_photo_is_not_in_the_file(): void
    {
        $category = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $shown = $this->product(['category_id' => $category->id]);
        $notInGoogle = $this->product(['category_id' => $category->id, 'show_in_google' => false]);
        $hidden = $this->product(['category_id' => $category->id, 'is_published' => false]);
        $withoutPhoto = Product::factory()->create(['category_id' => $category->id]);

        foreach ([$shown, $notInGoogle, $hidden, $withoutPhoto] as $product) {
            ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 2]);
        }

        $items = $this->items($this->get('/google-merchant.xml')->getContent());

        $this->assertSame(['v'.$shown->variants()->value('id')], array_keys($items));
    }

    public function test_a_mug_with_the_customers_text_and_a_voucher_promise_no_delivery_time_and_the_mug_cannot_be_returned(): void
    {
        $this->deliveryOptions();
        $mugs = $this->product([
            'name' => 'Kubki z cytatem',
            'stamp_enabled' => true,
            'category_id' => Category::factory()->create(['group' => CategoryGroup::Ceramics])->id,
        ]);
        $ownText = ProductVariant::factory()->create(['product_id' => $mugs->id, 'label' => 'Twój tekst', 'price_gross' => 7900, 'stock' => null]);
        $voucher = $this->product([
            'name' => 'Voucher kwotowy',
            'category_id' => Category::factory()->create(['name' => 'Vouchery', 'group' => CategoryGroup::Workshops])->id,
            'google_category' => GoogleCategory::GiftCards,
        ]);
        $amount = ProductVariant::factory()->create(['product_id' => $voucher->id, 'label' => '150 zł', 'price_gross' => 15000, 'stock' => null]);

        $items = $this->items($this->get('/google-merchant.xml')->getContent());

        $mug = $items['v'.$ownText->id];
        $this->assertSame('in_stock', (string) $mug->availability);
        $this->assertSame('personalizowane', (string) $mug->return_policy_label);
        $this->assertSame(['PL', 'InPost Paczkomat', '16.00 PLN'], $this->shipping($mug->shipping[0]));

        $card = $items['v'.$amount->id];
        $this->assertSame('in_stock', (string) $card->availability);
        $this->assertSame('53', (string) $card->google_product_category);
        $this->assertSame('Warsztaty > Vouchery', (string) $card->product_type);
        $this->assertCount(0, $card->return_policy_label);
        $this->assertSame(['PL', 'InPost Paczkomat', '16.00 PLN'], $this->shipping($card->shipping[0]));
    }

    public function test_a_gift_set_on_sale_goes_as_a_bundle_linked_to_its_place_on_the_sets_page(): void
    {
        $this->deliveryOptions();
        $category = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $mug = ProductVariant::factory()->create(['product_id' => $this->product(['name' => 'Kubek', 'category_id' => $category->id])->id, 'price_gross' => 9000, 'stock' => 3]);
        $scrunchie = ProductVariant::factory()->create(['product_id' => $this->product(['name' => 'Scrunchie', 'category_id' => $category->id])->id, 'price_gross' => 6000, 'stock' => 3]);
        $set = Bundle::factory()->create(['name' => 'Kubek i scrunchie', 'description' => null, 'discount_percent' => 10, 'is_published' => true]);
        BundleItem::factory()->create(['bundle_id' => $set->id, 'product_variant_id' => $mug->id, 'sort_order' => 1]);
        BundleItem::factory()->create(['bundle_id' => $set->id, 'product_variant_id' => $scrunchie->id, 'sort_order' => 2]);

        $item = $this->items($this->get('/google-merchant.xml')->getContent())['bundle-'.$set->id];

        $this->assertSame('Kubek i scrunchie', (string) $item->title);
        $this->assertSame('Zestaw: Kubek, Scrunchie', (string) $item->description);
        $this->assertSame(url('/zestawy-prezentowe').'#zestaw-'.$set->id, (string) $item->link);
        $this->assertSame('135.00 PLN', (string) $item->price);
        $this->assertSame('yes', (string) $item->is_bundle);
        $this->assertCount(1, $item->additional_image_link);
        $this->assertSame('Zestawy prezentowe', (string) $item->product_type);

        $this->get('/zestawy-prezentowe')->assertSee('id="zestaw-'.$set->id.'"', false);
    }

    public function test_the_mug_from_the_configurator_goes_once_per_size_linked_to_the_page_with_that_size_chosen(): void
    {
        $this->seed(SettingsSeeder::class);
        // The photo Kasia uploads in the panel; a photo from the site's build has no address while Vite is off in tests.
        Storage::disk('public')->put('mug/kubek-z-boku.webp', (string) file_get_contents(base_path('zdjecia/kubek-cappuccino.webp')));
        Setting::updateOrCreate(['key' => 'mug_configurator_image'], ['value' => 'mug/kubek-z-boku.webp']);

        $items = $this->items($this->get('/google-merchant.xml')->getContent());

        $this->assertSame(['mug-maly', 'mug-sredni', 'mug-duzy'], array_keys($items));
        $small = $items['mug-maly'];
        $this->assertSame('Kubek z napisem — Mały 200 ml', (string) $small->title);
        $this->assertSame(route('mug.index').'?rozmiar=maly', (string) $small->link);
        $this->assertSame('69.00 PLN', (string) $small->price);
        $this->assertSame('2169', (string) $small->google_product_category);
        $this->assertSame('personalizowane', (string) $small->return_policy_label);
        $this->assertStringEndsWith('/mug/kubek-z-boku.webp', (string) $small->image_link);
        $this->assertNotSame('', (string) $small->description);

        $this->get('/kubek-z-napisem?rozmiar=maly')
            ->assertOk()
            ->assertSee('value="Mały" x-model="size" checked', false)
            ->assertSeeInOrder(['Dodaj do koszyka', '69,00 zł']);
        $this->get('/kubek-z-napisem?rozmiar=nie-ma')->assertSee('value="Średni" x-model="size" checked', false);
    }

    public function test_the_mug_page_describes_every_size_for_google(): void
    {
        $this->seed(SettingsSeeder::class);

        $data = $this->structuredData($this->get('/kubek-z-napisem')->getContent());
        $group = $data->firstWhere('@type', 'ProductGroup');

        $this->assertSame('Kubek z napisem', $group['name']);
        $this->assertSame(route('mug.index'), $group['url']);
        $this->assertCount(3, $group['hasVariant']);
        $small = $group['hasVariant'][0];
        $this->assertSame('Kubek z napisem — Mały 200 ml', $small['name']);
        $this->assertSame('mug-maly', $small['sku']);
        $this->assertSame(route('mug.index').'?rozmiar=maly', $small['offers']['url']);
        $this->assertSame('69.00', $small['offers']['price']);
        $this->assertSame('https://schema.org/MerchantReturnNotPermitted', $small['offers']['hasMerchantReturnPolicy']['returnPolicyCategory']);
        $this->assertArrayNotHasKey('deliveryTime', $small['offers']['shippingDetails'][0]);
        $this->assertSame('BreadcrumbList', $data->last()['@type']);
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
        Setting::create(['key' => 'dispatch_days_min', 'value' => 3]);
        Setting::create(['key' => 'dispatch_days_max', 'value' => 5]);
        Setting::create(['key' => 'shipping_methods', 'value' => [
            ['code' => 'parcel_locker', 'label' => 'InPost Paczkomat', 'note' => '1–2 dni robocze', 'price_gross' => 1600],
            ['code' => 'courier', 'label' => 'Kurier InPost', 'note' => 'Do rąk, 1 dzień', 'price_gross' => 2200],
            ['code' => 'studio_pickup', 'label' => 'Odbiór w pracowni', 'note' => 'Kraków, po umówieniu', 'price_gross' => 0],
        ]]);
    }

    /**
     * The feed's items by id, with the g: elements as children.
     *
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
