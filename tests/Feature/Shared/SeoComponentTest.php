<?php

namespace Tests\Feature\Shared;

use App\Modules\Settings\Models\Setting;
use App\Modules\Shared\Support\BusinessStructuredData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoComponentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_a_page_gets_its_title_description_canonical_and_open_graph_tags(): void
    {
        $this->blade('<x-shared::layout title="Wazony — ceramika handmade | MellowAura" description="Wazon lepiony z ręki." canonical="https://mellow-aura.com/produkt/wazony" type="product" image="https://mellow-aura.com/storage/1/wazon.webp">Treść</x-shared::layout>')
            ->assertSee('<title>Wazony — ceramika handmade | MellowAura</title>', false)
            ->assertSee('<meta name="description" content="Wazon lepiony z ręki.">', false)
            ->assertSee('<link rel="canonical" href="https://mellow-aura.com/produkt/wazony">', false)
            ->assertSee('<meta property="og:title" content="Wazony — ceramika handmade | MellowAura">', false)
            ->assertSee('<meta property="og:description" content="Wazon lepiony z ręki.">', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee('<meta property="og:locale" content="pl_PL">', false)
            ->assertSee('<meta property="og:url" content="https://mellow-aura.com/produkt/wazony">', false)
            ->assertSee('<meta property="og:image" content="https://mellow-aura.com/storage/1/wazon.webp">', false)
            ->assertDontSee('<meta name="robots"', false);
    }

    public function test_every_page_describes_the_studio_with_its_city_but_never_its_street(): void
    {
        Setting::create(['key' => 'contact_phone', 'value' => '+48 600 100 200']);
        Setting::create(['key' => 'contact_email', 'value' => 'pracownia@example.com']);
        Setting::create(['key' => 'instagram_handle', 'value' => '@mellowaura']);
        Setting::create(['key' => 'facebook_url', 'value' => 'https://www.facebook.com/mellowaura']);
        Setting::create(['key' => 'studio_address', 'value' => 'ul. Przykładowa 7, 30-001 Kraków']);
        BusinessStructuredData::priceRangeUsing(fn () => [6990, 29950]);

        $html = (string) $this->blade('<x-shared::layout title="Sklep">Treść</x-shared::layout>');
        $business = $this->structuredData($html)->firstWhere('@type', 'LocalBusiness');

        $this->assertSame(url('/').'/#business', $business['@id']);
        $this->assertSame('MellowAura', $business['name']);
        $this->assertSame(['@type' => 'PostalAddress', 'addressLocality' => 'Kraków', 'addressCountry' => 'PL'], $business['address']);
        $this->assertSame('Katarzyna Samborska', $business['founder']['name']);
        $this->assertSame('+48600100200', $business['telephone']);
        $this->assertSame('pracownia@example.com', $business['email']);
        $this->assertSame(['https://www.instagram.com/mellowaura/', 'https://www.facebook.com/mellowaura'], $business['sameAs']);
        $this->assertSame('69–300 zł', $business['priceRange']);
        $this->assertArrayNotHasKey('aggregateRating', $business);
        $this->assertStringNotContainsString('Przykładowa', $html);
    }

    public function test_empty_settings_and_an_empty_shelf_are_left_out(): void
    {
        BusinessStructuredData::priceRangeUsing(fn () => null);

        $business = $this->structuredData((string) $this->blade('<x-shared::layout title="Sklep">Treść</x-shared::layout>'))
            ->firstWhere('@type', 'LocalBusiness');

        $this->assertSame(url('/'), $business['url']);

        foreach (['telephone', 'email', 'sameAs', 'priceRange'] as $key) {
            $this->assertArrayNotHasKey($key, $business);
        }
    }
}
