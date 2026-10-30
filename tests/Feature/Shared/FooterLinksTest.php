<?php

namespace Tests\Feature\Shared;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterLinksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
        Setting::query()->where('key', 'contact_phone')->update(['value' => json_encode('+48 600 100 200')]);
        Setting::query()->updateOrCreate(['key' => 'instagram_handle'], ['value' => 'mellowaura.studio']);
    }

    public function test_the_phone_number_rings_and_whatsapp_keeps_its_own_pill(): void
    {
        $footer = $this->footer('/sklep');

        $this->assertStringContainsString('<a href="tel:+48600100200" class="text-on-dark hover:text-rose">+48 600 100 200</a>', $footer);
        $this->assertSame(1, substr_count($footer, 'href="https://wa.me/48600100200"'));
        $this->assertMatchesRegularExpression('#href="https://wa.me/48600100200"[^>]*>WhatsApp <span aria-hidden="true">↗</span><span class="sr-only"> \(otwiera się w nowej karcie\)</span></a>#', $footer);
    }

    public function test_every_link_that_opens_a_new_tab_says_so(): void
    {
        foreach (['/sklep' => 'otwiera się w nowej karcie', '/en/shop' => 'opens in a new tab'] as $page => $note) {
            $footer = $this->footer($page);

            $this->assertGreaterThan(0, substr_count($footer, 'target="_blank"'), $page);
            $this->assertSame(substr_count($footer, 'target="_blank"'), substr_count($footer, '<span class="sr-only"> ('.$note.')</span>'), $page);
        }
    }

    public function test_each_link_leads_to_a_different_page_and_about_and_the_studio_are_there(): void
    {
        $footer = $this->footer('/sklep');

        $this->assertSame(1, substr_count($footer, 'href="'.route('content.contact').'"'));
        $this->assertSame(1, substr_count($footer, 'href="'.route('content.faq').'"'));
        $this->assertStringContainsString('<span>Pracownia na zapisy</span>', $footer);
        $this->assertStringContainsString('<a href="'.route('content.about').'" class="text-on-dark hover:text-rose">O mnie</a>', $footer);
        $this->assertStringContainsString('<a href="'.route('content.studio').'" class="text-on-dark hover:text-rose">Moja pracownia</a>', $footer);
        // The wording the EU directive gives the withdrawal button (2023/2673, art. 11a) stays as it is.
        $this->assertStringContainsString('>Odstąp od umowy tutaj</a>', $footer);

        $english = $this->footer('/en/shop');
        $this->assertStringContainsString('<a href="'.url('/en/about').'" class="text-on-dark hover:text-rose">About me</a>', $english);
        $this->assertStringContainsString('<a href="'.url('/en/studio').'" class="text-on-dark hover:text-rose">The studio</a>', $english);
    }

    private function footer(string $page): string
    {
        $html = $this->get($page)->assertOk()->getContent();

        return substr($html, strpos($html, '<footer'));
    }
}
