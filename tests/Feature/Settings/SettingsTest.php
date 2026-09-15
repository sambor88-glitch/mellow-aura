<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_values_count_as_missing(): void
    {
        Setting::create(['key' => 'footer_city', 'value' => 'Kraków, Polska']);
        Setting::create(['key' => 'contact_email', 'value' => null]);
        Setting::create(['key' => 'text_footer_tagline', 'value' => '']);

        $settings = app(Settings::class);

        $this->assertSame('Kraków, Polska', $settings->get('footer_city'));
        $this->assertNull($settings->get('contact_email'));
        $this->assertSame('brak', $settings->get('text_footer_tagline', 'brak'));
        $this->assertSame('brak', $settings->get('unknown_key', 'brak'));
    }
}
