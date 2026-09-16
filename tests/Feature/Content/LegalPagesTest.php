<?php

namespace Tests\Feature\Content;

use App\Modules\Content\Support\LegalDocument;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(SettingsSeeder::class);
    }

    public function test_the_terms_show_the_version_the_contents_and_mark_the_draft(): void
    {
        $this->get('/regulamin')
            ->assertOk()
            ->assertSee('<title>Regulamin sklepu | MellowAura</title>', false)
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertSeeInOrder(['dokumenty sklepu', 'Regulamin sklepu internetowego MellowAura', 'Wersja 0.2 z 16 września 2026', 'obowiązuje od startu sklepu'])
            ->assertSee('To projekt, który sprawdza jeszcze prawnik.')
            ->assertSeeInOrder(['Spis treści', '§1. Postanowienia ogólne', '§12. Prawo odstąpienia od umowy', 'Załącznik nr 1. Wzór formularza odstąpienia od umowy'])
            ->assertSee('id="regulamin-12-prawo-odstapienia-od-umowy"', false)
            ->assertDontSee('do prawnika')
            ->assertDontSee('Art. 43b ust. 4 u.p.k.');
    }

    public function test_the_sellers_details_come_from_the_panel_and_an_empty_one_stays_marked(): void
    {
        foreach (['company_name' => 'MellowAura Katarzyna Samborska', 'company_nip' => '1111111111', 'contact_email' => 'kasia@example.com', 'voucher_validity_months' => 12] as $key => $value) {
            Setting::query()->where('key', $key)->update(['value' => json_encode($value)]);
        }

        $html = $this->get('/regulamin')->assertOk()->getContent();

        $this->assertStringContainsString('pod firmą MellowAura Katarzyna Samborska, wpisana', $html);
        $this->assertStringContainsString('NIP 1111111111, REGON <mark>REGON</mark>', $html);
        $this->assertStringContainsString('e-mail: <a href="mailto:kasia@example.com">kasia@example.com</a>', $html);
        $this->assertStringContainsString('Voucher jest ważny 12 miesięcy', $html);
        $this->assertStringContainsString('na stronie <a href="'.route('content.privacy').'">', $html);
        $this->assertStringContainsString('są podane na stronie <a href="'.route('workshops.index').'">', $html);
        $this->assertStringContainsString('<mark>REGON</mark>', $html);
        $this->assertStringNotContainsString('data-fill', $html);
    }

    public function test_a_value_from_the_panel_is_escaped(): void
    {
        Setting::query()->where('key', 'company_name')->update(['value' => json_encode('<script>alert(1)</script>')]);

        $this->get('/polityka-prywatnosci')
            ->assertOk()
            ->assertSee('Polityka prywatności MellowAura')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_the_footer_links_to_both_documents(): void
    {
        $this->get('/sklep')
            ->assertSee('href="'.route('content.terms').'"', false)
            ->assertSee('href="'.route('content.privacy').'"', false);
    }

    public function test_an_order_can_name_the_accepted_version(): void
    {
        $this->assertSame('wersja 0.2 z 16 września 2026', LegalDocument::terms()->versionLabel());
    }
}
