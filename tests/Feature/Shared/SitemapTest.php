<?php

namespace Tests\Feature\Shared;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_sitemap_lists_the_pages_categories_and_published_products_with_photos(): void
    {
        Storage::fake('public');
        $mugs = Category::factory()->create(['slug' => 'kubki-i-filizanki']);
        $empty = Category::factory()->create(['slug' => 'kosmetyczki-i-piorniki']);
        $mug = Product::factory()->create(['slug' => 'kubki-z-cytatem', 'name' => 'Kubki z cytatem', 'category_id' => $mugs->id]);
        ProductVariant::factory()->create(['product_id' => $mug->id, 'stock' => 3]);
        $photo = $mug->addMedia(UploadedFile::fake()->image('kubek.jpg'))->withCustomProperties(['alt' => 'Kubek z napisem w dłoni'])->toMediaCollection('images');
        $sold = Product::factory()->create(['slug' => 'wazony', 'category_id' => $mugs->id]);
        ProductVariant::factory()->create(['product_id' => $sold->id, 'stock' => 0]);
        $draft = Product::factory()->create(['slug' => 'kosmetyczki', 'is_published' => false, 'category_id' => $empty->id]);
        ProductVariant::factory()->create(['product_id' => $draft->id]);

        $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'text/xml; charset=utf-8');
        $xml = $response->getContent();

        foreach (['home', 'shop.index', 'content.about', 'content.studio', 'content.scarf', 'content.imprint', 'content.faq', 'content.contact', 'workshops.index', 'firing.index', 'mug.index', 'gifts.index', 'bundles.index'] as $route) {
            $this->assertStringContainsString('<loc>'.route($route).'</loc>', $xml, $route);
        }

        $this->assertStringContainsString('<loc>'.route('shop.category', $mugs).'</loc>', $xml);
        $this->assertStringNotContainsString(route('shop.category', $empty), $xml);
        $this->assertMatchesRegularExpression('#<loc>'.preg_quote(route('product.show', $mug), '#').'</loc>\s*<lastmod>[^<]+</lastmod>\s*<image:image>\s*<image:loc>'.preg_quote(url($photo->getUrl()), '#').'</image:loc>\s*<image:caption>Kubek z napisem w dłoni</image:caption>\s*<image:title>Kubki z cytatem</image:title>#', $xml);
        // A sold piece keeps its page, so it stays in the map; a draft doesn't.
        $this->assertStringContainsString('<loc>'.route('product.show', $sold).'</loc>', $xml);
        $this->assertStringNotContainsString('/produkt/kosmetyczki', $xml);

        // The terms and the privacy policy are drafts with notes for the lawyer and noindex (MA-220).
        foreach (['content.terms', 'content.privacy'] as $route) {
            $this->assertStringNotContainsString(route($route), $xml, $route);
        }

        foreach (['/zamowienie<', '/panel', '/ustawienia-cookies', '/odstapienie-od-umowy', '/voucher/'] as $private) {
            $this->assertStringNotContainsString(url('/').$private, $xml, $private);
        }

        foreach (['custom-orders.index', 'content.b2b', 'vouchers.index'] as $route) {
            $this->assertStringContainsString('<loc>'.route($route).'</loc>', $xml, $route);
        }
    }

    public function test_a_page_in_two_languages_is_listed_in_both_and_each_entry_points_to_both(): void
    {
        Storage::fake('public');
        $mugs = Category::factory()->create(['slug' => 'kubki-i-filizanki']);
        $mugs->translations()->create(['locale' => 'en', 'name' => 'Mugs and cups', 'slug' => 'mugs-and-cups']);
        $mug = Product::factory()->create(['slug' => 'malowany-kubek', 'name' => 'Malowany kubek', 'category_id' => $mugs->id]);
        $mug->translations()->create(['locale' => 'en', 'name' => 'Painted mug', 'slug' => 'painted-mug']);
        ProductVariant::factory()->create(['product_id' => $mug->id, 'stock' => 3])->prices()->create(['currency' => 'EUR', 'amount_minor' => 3900]);
        $mug->addMedia(UploadedFile::fake()->image('kubek.jpg'))->toMediaCollection('images');
        // Translated but without a price in euro: the English page does not exist, so neither does its entry.
        $vase = Product::factory()->create(['slug' => 'wazon', 'category_id' => $mugs->id]);
        $vase->translations()->create(['locale' => 'en', 'name' => 'Vase', 'slug' => 'vase']);
        ProductVariant::factory()->create(['product_id' => $vase->id, 'stock' => 1]);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $pairs = [
            [url('/sklep'), url('/en/shop')],
            [url('/sklep/kubki-i-filizanki'), url('/en/shop/mugs-and-cups')],
            [url('/produkt/malowany-kubek'), url('/en/product/painted-mug')],
        ];

        foreach ($pairs as [$polish, $english]) {
            foreach ([$polish, $english] as $loc) {
                $this->assertMatchesRegularExpression('#<loc>'.preg_quote($loc, '#').'</loc>\s*'
                    .'<xhtml:link rel="alternate" hreflang="pl" href="'.preg_quote($polish, '#').'" />\s*'
                    .'<xhtml:link rel="alternate" hreflang="en" href="'.preg_quote($english, '#').'" />\s*'
                    .'<xhtml:link rel="alternate" hreflang="x-default" href="'.preg_quote($polish, '#').'" />#', $xml, $loc);
            }
        }

        // Each entry names the product in its own language.
        $this->assertStringContainsString('<image:title>Painted mug</image:title>', $xml);
        $this->assertStringContainsString('<image:title>Malowany kubek</image:title>', $xml);

        // A Polish-only page is listed once, with nothing to point to.
        $this->assertMatchesRegularExpression('#<loc>'.preg_quote(route('workshops.index'), '#').'</loc>\s*</url>#', $xml);
        $this->assertMatchesRegularExpression('#<loc>'.preg_quote(url('/produkt/wazon'), '#').'</loc>\s*<lastmod>#', $xml);
        $this->assertStringNotContainsString('/en/product/vase<', $xml);
    }

    public function test_with_english_switched_off_the_sitemap_is_polish_only(): void
    {
        config(['localization.enabled' => ['pl']]);
        $mugs = Category::factory()->create();
        $mugs->translations()->create(['locale' => 'en', 'name' => 'Mugs', 'slug' => 'mugs']);

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString('/en/', $xml);
        $this->assertStringNotContainsString('xhtml:link', $xml);
    }

    public function test_robots_keeps_crawlers_out_of_the_panel_only_and_points_to_the_sitemap(): void
    {
        // A static file: Forge's nginx answers /robots.txt itself and never passes it to Laravel.
        $this->assertSame("User-agent: *\nDisallow: /panel\n\nSitemap: https://mellow-aura.com/sitemap.xml\n", file_get_contents(public_path('robots.txt')));
    }
}
