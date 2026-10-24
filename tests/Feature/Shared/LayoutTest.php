<?php

namespace Tests\Feature\Shared;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Settings\Actions\UpdateSetting;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Shared\Seo\BusinessSchema;
use App\Modules\Shared\Seo\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://mellow-aura.com', 'staging.password' => '']);
        $this->seed(SettingsSeeder::class);
    }

    public function test_page_has_one_set_of_head_tags_with_absolute_canonical(): void
    {
        $this->get('/?utm_source=instagram')
            ->assertOk()
            ->assertSee('<html lang="pl">', false)
            ->assertSee('<title>MellowAura — ceramika i rękodzieło z jedwabiu, Kraków</title>', false)
            ->assertSee('<meta name="description" content="Ręcznie formowana ceramika', false)
            ->assertSee('<link rel="canonical" href="https://mellow-aura.com/">', false)
            ->assertSee('<meta property="og:url" content="https://mellow-aura.com/">', false)
            ->assertSee('<meta property="og:locale" content="pl_PL">', false);
    }

    public function test_canonical_uses_the_current_path_without_query_or_trailing_slash(): void
    {
        $this->get('/sklep/?sort=price');

        $this->assertSame('https://mellow-aura.com/sklep', (new Seo('Sklep'))->canonical());
    }

    public function test_header_and_footer_link_to_real_addresses(): void
    {
        $this->get('/')
            ->assertSee('href="'.url('/sklep').'"', false)
            ->assertSee('href="'.url('/warsztaty-ceramiczne-krakow').'"', false)
            ->assertSee('href="'.url('/zamowienie').'"', false)
            ->assertDontSee('onClick', false)
            ->assertSee('class="ma-footer focus-on-dark"', false);
    }

    public function test_footer_hides_contact_fields_left_empty_in_the_panel(): void
    {
        $this->get('/')
            ->assertDontSee('mailto:', false)
            ->assertDontSee('wa.me', false)
            ->assertSee('instagram.com/mellowaura.ceramika/', false)
            ->assertSee('Kraków, Polska');
    }

    public function test_footer_shows_contact_fields_once_filled_in(): void
    {
        app(UpdateSetting::class)->handle('contact.email', 'pracownia@example.com');
        app(UpdateSetting::class)->handle('contact.phone', '+48 600 100 200');

        $this->get('/')
            ->assertSee('href="mailto:pracownia@example.com"', false)
            ->assertSee('href="https://wa.me/48600100200"', false)
            ->assertSee('+48 600 100 200');
    }

    public function test_business_schema_has_no_street_address_and_skips_empty_fields(): void
    {
        $schema = app(BusinessSchema::class)->toArray();

        $this->assertSame('LocalBusiness', $schema['@type']);
        $this->assertSame('https://mellow-aura.com/#pracownia', $schema['@id']);
        $this->assertSame(['@type' => 'PostalAddress', 'addressLocality' => 'Kraków', 'addressCountry' => 'PL'], $schema['address']);
        $this->assertArrayNotHasKey('email', $schema);
        $this->assertArrayNotHasKey('telephone', $schema);
        $this->assertArrayNotHasKey('aggregateRating', $schema);
        $this->assertSame([
            'https://www.instagram.com/mellowaura.ceramika/',
            'https://facebook.com/profile.php?id=61552376291595',
        ], $schema['sameAs']);
    }

    public function test_price_range_comes_from_published_products_only(): void
    {
        $published = Product::factory()->create(['is_published' => true]);
        ProductVariant::factory()->for($published)->create(['price_gross' => 5900]);
        ProductVariant::factory()->for($published)->create(['price_gross' => 49850]);
        $draft = Product::factory()->create(['is_published' => false]);
        ProductVariant::factory()->for($draft)->create(['price_gross' => 99900]);

        $this->assertSame('59–499 zł', app(BusinessSchema::class)->toArray()['priceRange']);
    }

    public function test_json_ld_cannot_close_the_script_tag(): void
    {
        $html = Blade::render('<x-shared::seo :seo="$seo" />', [
            'seo' => new Seo('Test', schema: [['@type' => 'Thing', 'name' => '</script><script>alert(1)</script>']]),
        ]);

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
        $this->assertStringContainsString('</script>', $html);
    }

    public function test_robots_meta_only_when_asked_for(): void
    {
        $this->assertStringNotContainsString('name="robots"', Blade::render('<x-shared::seo :seo="$seo" />', ['seo' => new Seo('Sklep')]));
        $this->assertStringContainsString(
            '<meta name="robots" content="noindex, nofollow">',
            Blade::render('<x-shared::seo :seo="$seo" />', ['seo' => new Seo('Zamówienie', robots: 'noindex, nofollow')]),
        );
    }
}
