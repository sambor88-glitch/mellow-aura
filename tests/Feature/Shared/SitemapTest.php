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

        foreach (['home', 'shop.index', 'content.about', 'content.studio', 'content.scarf', 'content.imprint', 'content.faq', 'content.contact', 'content.terms', 'content.privacy', 'workshops.index', 'firing.index', 'mug.index', 'gifts.index', 'bundles.index'] as $route) {
            $this->assertStringContainsString('<loc>'.route($route).'</loc>', $xml, $route);
        }

        $this->assertStringContainsString('<loc>'.route('shop.category', $mugs).'</loc>', $xml);
        $this->assertStringNotContainsString(route('shop.category', $empty), $xml);
        $this->assertMatchesRegularExpression('#<loc>'.preg_quote(route('product.show', $mug), '#').'</loc>\s*<lastmod>[^<]+</lastmod>\s*<image:image>\s*<image:loc>'.preg_quote(url($photo->getUrl()), '#').'</image:loc>\s*<image:caption>Kubek z napisem w dłoni</image:caption>\s*<image:title>Kubki z cytatem</image:title>#', $xml);
        // A sold piece keeps its page, so it stays in the map; a draft doesn't.
        $this->assertStringContainsString('<loc>'.route('product.show', $sold).'</loc>', $xml);
        $this->assertStringNotContainsString('/produkt/kosmetyczki', $xml);

        foreach (['/zamowienie<', '/panel', '/ustawienia-cookies', '/odstapienie-od-umowy', '/voucher/'] as $private) {
            $this->assertStringNotContainsString(url('/').$private, $xml, $private);
        }

        foreach (['custom-orders.index', 'content.b2b', 'vouchers.index'] as $route) {
            $this->assertStringContainsString('<loc>'.route($route).'</loc>', $xml, $route);
        }
    }

    public function test_robots_keeps_crawlers_out_of_the_panel_only_and_points_to_the_sitemap(): void
    {
        // A static file: Forge's nginx answers /robots.txt itself and never passes it to Laravel.
        $this->assertSame("User-agent: *\nDisallow: /panel\n\nSitemap: https://mellow-aura.com/sitemap.xml\n", file_get_contents(public_path('robots.txt')));
    }
}
