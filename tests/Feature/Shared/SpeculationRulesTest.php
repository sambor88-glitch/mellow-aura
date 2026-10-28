<?php

namespace Tests\Feature\Shared;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SpeculationRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_page_lists_only_pages_that_read_and_change_nothing(): void
    {
        $rule = $this->rules($this->get('/sklep')->assertOk()->getContent())['prerender'][0];
        [$pages, $skip] = $rule['where']['and'];

        $this->assertSame('moderate', $rule['eagerness']);
        foreach (['/', '/sklep', '/sklep/*', '/produkt/*', '/kubek-z-napisem', '/o-mnie', '/en/shop', '/en/product/*'] as $pattern) {
            $this->assertContains($pattern, $pages['href_matches']);
        }

        // The cart, checkout and payment, the panel and a bought piece's page with its throttle stay plain links.
        foreach ($pages['href_matches'] as $pattern) {
            $this->assertDoesNotMatchRegularExpression('#^/(en/)?(koszyk|basket|zamowienie|checkout|panel|k/|przelewy24)#', $pattern);
        }

        // So do links with a query string, the language switch and other sites.
        $this->assertStringContainsString('[href*="?"]', $skip['not']['selector_matches']);
        $this->assertStringContainsString('[data-locale-choice]', $skip['not']['selector_matches']);
        $this->assertStringStartsWith('/', $pages['href_matches'][0]);
    }

    public function test_the_panel_gets_no_rules(): void
    {
        $this->get('/panel/logowanie')->assertOk()->assertDontSee('speculationrules', false);
    }

    public function test_a_prerender_reads_the_session_but_never_writes_it(): void
    {
        $vase = $this->product();
        $cookie = config('session.cookie');

        $this->post('/koszyk', ['variant_id' => $vase->id, 'quantity' => 1])->assertRedirect();
        $id = session()->getId();
        $saved = $this->stored($id);
        $this->assertCount(1, $saved['cart']);

        // The prerendered page shows the cart as it is…
        $this->withCookie($cookie, $id)
            ->withHeader('Sec-Purpose', 'prefetch;prerender')
            ->get('/o-mnie')
            ->assertOk();

        // …but what it read stays exactly as it was stored, „previous page” included.
        $this->assertSame($saved, $this->stored($id));

        // A page the customer opens still saves the session.
        $this->flushHeaders()->withCookie($cookie, $id)->get('/o-mnie')->assertOk();
        $this->assertStringEndsWith('/o-mnie', $this->stored($id)['_previous']['url']);
    }

    public function test_analytics_counts_a_prerendered_page_only_once_it_is_shown(): void
    {
        $this->seed(SettingsSeeder::class);
        Setting::query()->where('key', 'google_analytics_id')->update(['value' => json_encode('G-AB12CD34EF')]);

        $html = $this->withCookie('mellowaura-consent', '1.analytics.'.now()->timestamp)->get('/kontakt')->getContent();

        $this->assertMatchesRegularExpression("#document\.prerendering \? document\.addEventListener\('prerenderingchange', start, \{ once: true \}\) : start\(\);\s*\}\)\(function \(\) \{\s*gtag\('js', new Date\(\)\);\s*gtag\('config', 'G-AB12CD34EF'\);#", $html);
    }

    /** @return array<string, mixed> */
    private function rules(string $html): array
    {
        $this->assertSame(1, preg_match('#<script type="speculationrules" data-speculation-rules>(.*?)</script>#s', $html, $match));

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    /** @return array<string, mixed> */
    private function stored(string $id): array
    {
        // config/session.php stores the session as JSON.
        return json_decode(app('session')->driver()->getHandler()->read($id), true, flags: JSON_THROW_ON_ERROR);
    }

    private function product(): ProductVariant
    {
        $product = Product::factory()->create([
            'name' => 'Wazony',
            'slug' => 'wazony',
            'category_id' => Category::factory()->create()->id,
            'is_published' => true,
            'stamp_enabled' => false,
        ]);

        return ProductVariant::factory()->create(['product_id' => $product->id, 'label' => 'Niski 16 cm', 'price_gross' => 23900, 'stock' => 3]);
    }
}
