<?php

namespace Tests\Feature\Catalog;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Enums\FoodContact;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProductPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_product_page_shows_the_offer_for_the_chosen_variant(): void
    {
        Setting::create(['key' => 'care_rule_ceramics', 'value' => 'Zmywarka tak, złoto tylko ręcznie']);
        Setting::create(['key' => 'free_shipping_threshold', 'value' => 40000]);
        Setting::create(['key' => 'dispatch_days_min', 'value' => 3]);
        Setting::create(['key' => 'dispatch_days_max', 'value' => 5]);
        $vases = Category::factory()->create(['name' => 'Wazony i patery', 'group' => CategoryGroup::Ceramics]);
        $product = Product::factory()->create([
            'slug' => 'wazony',
            'name' => 'Wazony',
            'category_id' => $vases->id,
            'description' => 'Wazon lepiony z ręki.',
            'dimensions' => ['height_cm' => '24', 'thickness_mm' => '6'],
        ]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => 3]);
        $tall = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Wysoki 24 cm', 'price_gross' => 29900, 'stock' => 1]);

        $this->get('/produkt/wazony')
            ->assertOk()
            ->assertSee('<title>Wazony — ceramika handmade | MellowAura</title>', false)
            ->assertSee('<link rel="canonical" href="'.url('/produkt/wazony').'">', false)
            ->assertSee('Wazon lepiony z ręki.')
            ->assertSee('239,00 zł')
            ->assertSee(['Wysokość', '24 cm', 'Grubość', '6 mm'])
            ->assertSee('Zmywarka tak, złoto tylko ręcznie')
            ->assertSee('3–5 dni roboczych, gratis od 400,00 zł')
            ->assertDontSee('ostatnia sztuka');

        $this->get('/produkt/wazony?wariant='.$tall->id)
            ->assertOk()
            ->assertSee('ostatnia sztuka — kolejną zrobię na zamówienie');
    }

    public function test_the_page_describes_every_variant_for_search_engines(): void
    {
        $this->shippingSettings();
        $mugs = Category::factory()->create(['name' => 'Kubki i filiżanki', 'slug' => 'kubki-i-filizanki']);
        $product = Product::factory()->create(['slug' => 'kubki-z-cytatem', 'name' => 'Kubki z cytatem', 'category_id' => $mugs->id, 'stamp_enabled' => true]);
        $sold = ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Królowa matka', 'price_gross' => 7900, 'stock' => 0]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Twój tekst', 'price_gross' => 7900, 'stock' => null]);

        $html = $this->get('/produkt/kubki-z-cytatem')
            ->assertOk()
            ->assertSee('<meta property="og:type" content="product">', false)
            ->getContent();

        $blocks = $this->structuredData($html);
        $group = $blocks->firstWhere('@type', 'ProductGroup');
        $breadcrumbs = $blocks->firstWhere('@type', 'BreadcrumbList');

        $this->assertSame('kubki-z-cytatem', $group['productGroupID']);
        $this->assertCount(2, $group['hasVariant']);
        $this->assertSame('79.00', $group['hasVariant'][0]['offers']['price']);
        $this->assertSame('https://schema.org/OutOfStock', $group['hasVariant'][0]['offers']['availability']);
        $this->assertSame('https://schema.org/InStock', $group['hasVariant'][1]['offers']['availability']);
        $this->assertSame(url('/produkt/kubki-z-cytatem').'?wariant='.$sold->id, $group['hasVariant'][0]['offers']['url']);
        $this->assertSame(['Sklep', 'Kubki i filiżanki', 'Kubki z cytatem'], array_column($breadcrumbs['itemListElement'], 'name'));

        // A mug from the shelf: both parcel options at their price, how long it takes, 14 days to return it.
        $shelf = $group['hasVariant'][0]['offers'];
        $this->assertSame(
            [['InPost Paczkomat', '16.00', 'PLN', 'PL', [3, 5], [1, 2]], ['Kurier InPost', '22.00', 'PLN', 'PL', [3, 5], [1, 1]]],
            array_map(fn (array $details) => [
                $details['shippingLabel'],
                $details['shippingRate']['value'],
                $details['shippingRate']['currency'],
                $details['shippingDestination']['addressCountry'],
                [$details['deliveryTime']['handlingTime']['minValue'], $details['deliveryTime']['handlingTime']['maxValue']],
                [$details['deliveryTime']['transitTime']['minValue'], $details['deliveryTime']['transitTime']['maxValue']],
            ], $shelf['shippingDetails']),
        );
        $this->assertSame('DAY', $shelf['shippingDetails'][0]['deliveryTime']['handlingTime']['unitCode']);
        $this->assertSame([
            '@type' => 'MerchantReturnPolicy',
            'applicableCountry' => 'PL',
            'returnPolicyCategory' => 'https://schema.org/MerchantReturnFiniteReturnWindow',
            'merchantReturnDays' => 14,
            'returnMethod' => 'https://schema.org/ReturnByMail',
            'returnFees' => 'https://schema.org/ReturnFeesCustomerResponsibility',
        ], $shelf['hasMerchantReturnPolicy']);

        // A mug with the customer's own text is made for them: no return and no promised dispatch time.
        $ownText = $group['hasVariant'][1]['offers'];
        $this->assertSame('https://schema.org/MerchantReturnNotPermitted', $ownText['hasMerchantReturnPolicy']['returnPolicyCategory']);
        $this->assertArrayNotHasKey('merchantReturnDays', $ownText['hasMerchantReturnPolicy']);
        $this->assertCount(2, $ownText['shippingDetails']);
        $this->assertArrayNotHasKey('deliveryTime', $ownText['shippingDetails'][0]);
    }

    public function test_shipping_is_free_from_the_threshold_and_a_voucher_has_no_parcel_or_return_in_its_data(): void
    {
        $this->shippingSettings();
        $vases = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $vase = Product::factory()->create(['slug' => 'patera', 'category_id' => $vases->id]);
        ProductVariant::factory()->create(['product_id' => $vase->id, 'price_gross' => 42000, 'stock' => 1]);
        $vouchers = Category::factory()->create(['group' => CategoryGroup::Workshops]);
        $voucher = Product::factory()->create(['slug' => 'voucher-para', 'category_id' => $vouchers->id]);
        ProductVariant::factory()->create(['product_id' => $voucher->id, 'price_gross' => 39000, 'stock' => null]);

        $offer = $this->structuredData($this->get('/produkt/patera')->getContent())->firstWhere('@type', 'ProductGroup')['hasVariant'][0]['offers'];
        $this->assertSame(['0.00', '0.00'], array_column(array_column($offer['shippingDetails'], 'shippingRate'), 'value'));

        $response = $this->get('/produkt/voucher-para')->assertDontSee('3–5 dni roboczych');
        $offer = $this->structuredData($response->getContent())->firstWhere('@type', 'ProductGroup')['hasVariant'][0]['offers'];
        $this->assertSame('390.00', $offer['price']);
        $this->assertArrayNotHasKey('shippingDetails', $offer);
        $this->assertArrayNotHasKey('hasMerchantReturnPolicy', $offer);
    }

    public function test_the_page_says_what_the_terms_and_the_safety_rules_require(): void
    {
        Storage::fake('public');
        Setting::create(['key' => 'size_tolerance', 'value' => '0,5 cm']);
        Setting::create(['key' => 'company_name', 'value' => 'MellowAura Katarzyna Samborska']);
        Setting::create(['key' => 'company_address', 'value' => 'ul. Wirtualna 1, 30-001 Kraków']);
        Setting::create(['key' => 'contact_email', 'value' => 'kasia@mellow-aura.com']);
        $plates = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $plate = Product::factory()->create([
            'slug' => 'talerz-ze-zlotem',
            'category_id' => $plates->id,
            'dimensions' => ['diameter_cm' => '24'],
            'food_contact' => FoodContact::NotSuitable,
            'deviation' => 'Nie do zmywarki ani mikrofalówki — złota krawędź.',
            'safety_warnings' => 'Nie stawiaj na ogniu.',
            'is_exact_piece' => true,
        ]);
        ProductVariant::factory()->create(['product_id' => $plate->id, 'stock' => 2]);
        $plate->addMedia(base_path('zdjecia/talerz-niebieski-odcisk.webp'))->preservingOriginal()->toMediaCollection('images');

        $this->get('/produkt/talerz-ze-zlotem')
            ->assertOk()
            ->assertSeeInOrder(['ta sztuka', 'Na zdjęciach jest dokładnie rzecz, którą dostaniesz.'])
            ->assertSeeInOrder(['Średnica', '24 cm', 'Nie do kontaktu z żywnością', 'Ręczna robota — wymiary mogą różnić się do 0,5 cm.'])
            ->assertSeeInOrder(['Zwróć uwagę:', 'Nie do zmywarki ani mikrofalówki — złota krawędź. Przy zamówieniu poproszę, żebyś to potwierdziła osobnym polem.'])
            ->assertSee('W paczce karta z numerem, datą wypału i podpisem')
            ->assertSeeInOrder(['Ostrzeżenia', 'Nie stawiaj na ogniu.', 'Producent', 'MellowAura Katarzyna Samborska', 'ul. Wirtualna 1, 30-001 Kraków', 'href="mailto:kasia@mellow-aura.com"'], false);

        $plate->update(['is_exact_piece' => false]);
        $this->get('/produkt/talerz-ze-zlotem')
            ->assertSee('Zdjęcia pokazują przykładową sztukę — każdą robię ręcznie, więc Twoja będzie trochę inna.')
            ->assertDontSee('Na zdjęciach jest dokładnie rzecz');

        // Silk has its own tolerance and no firing date; without photos there is nothing to say about them.
        $silk = Category::factory()->create(['group' => CategoryGroup::Crafts]);
        $scrunchie = Product::factory()->create(['slug' => 'scrunchie', 'category_id' => $silk->id, 'dimensions' => ['circumference_cm' => '18'], 'size_tolerance' => '1 cm']);
        ProductVariant::factory()->create(['product_id' => $scrunchie->id, 'stock' => 3]);

        $this->get('/produkt/scrunchie')
            ->assertSee('wymiary mogą różnić się do 1 cm.')
            ->assertSee('W paczce karta z numerem i podpisem')
            ->assertDontSee('Zdjęcia pokazują przykładową sztukę')
            ->assertDontSee('Zwróć uwagę:')
            ->assertDontSee('kontaktu z żywnością')
            ->assertSee('Producent');

        // A voucher is no parcel: no certificate, no custom order box and no producer.
        $vouchers = Category::factory()->create(['group' => CategoryGroup::Workshops]);
        $voucher = Product::factory()->create(['slug' => 'voucher', 'category_id' => $vouchers->id, 'dimensions' => ['width_cm' => '21']]);
        ProductVariant::factory()->create(['product_id' => $voucher->id, 'stock' => null]);

        $this->get('/produkt/voucher')
            ->assertOk()
            ->assertDontSee('Certyfikat unikatu')
            ->assertDontSee('Chcesz inaczej?')
            ->assertDontSee('Producent')
            ->assertDontSee('mogą różnić się');
    }

    public function test_drafts_and_unknown_products_are_not_found(): void
    {
        $draft = Product::factory()->create(['slug' => 'miski', 'is_published' => false]);
        ProductVariant::factory()->create(['product_id' => $draft->id]);

        $this->get('/produkt/miski')->assertNotFound();
        $this->get('/produkt/nie-ma-takiego')->assertNotFound();
    }

    public function test_a_discount_shows_the_lowest_price_from_the_30_days_before_it(): void
    {
        $product = Product::factory()->create(['slug' => 'talerze']);

        $this->travelTo(now()->subDays(45));
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => 15900]);
        $this->travelBack();

        $this->travelTo(now()->subDays(20));
        $variant->update(['price_gross' => 14500]);
        $this->travelBack();

        $this->travelTo(now()->subDays(5));
        $variant->update(['price_gross' => 12900, 'compare_at_price' => 15900]);
        $this->travelBack();

        $this->assertSame(14500, $variant->fresh()->lowestPriceBeforeDiscount());

        $this->get('/produkt/talerze')
            ->assertOk()
            ->assertSee('129,00 zł')
            ->assertSee('159,00 zł')
            ->assertSee('Najniższa cena z 30 dni przed obniżką: 145,00 zł');
    }

    public function test_a_discount_without_an_earlier_price_is_not_shown(): void
    {
        $product = Product::factory()->create(['slug' => 'kubki']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'price_gross' => 7900, 'compare_at_price' => 9900]);

        $this->get('/produkt/kubki')
            ->assertOk()
            ->assertDontSee('99,00 zł')
            ->assertDontSee('Najniższa cena');
    }

    public function test_related_products_come_from_the_same_category_first_then_the_same_group(): void
    {
        $plates = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $vases = Category::factory()->create(['group' => CategoryGroup::Ceramics]);
        $silk = Category::factory()->create(['group' => CategoryGroup::Crafts]);

        $this->shelf('Talerz obiadowy', $vases, 1);
        $this->shelf('Opaska jedwabna', $silk, 2);
        $this->shelf('Talerz deserowy', $plates, 3);
        $this->shelf('Talerz serwisowy', $plates, 4);

        $this->get('/produkt/talerz-serwisowy')
            ->assertOk()
            ->assertSeeInOrder(['Z tej samej półki', 'Talerz deserowy', 'Talerz obiadowy'])
            ->assertDontSee('Opaska jedwabna');
    }

    private function shelf(string $name, Category $category, int $sortOrder): Product
    {
        $product = Product::factory()->create([
            'name' => $name,
            'slug' => str($name)->slug()->toString(),
            'category_id' => $category->id,
            'sort_order' => $sortOrder,
            'description' => null,
        ]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 2]);

        return $product;
    }

    /**
     * Delivery as seeded: parcel locker and courier, pickup at the studio, free from 400 zł, 3–5 days to dispatch.
     */
    private function shippingSettings(): void
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
}
