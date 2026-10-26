<?php

namespace Tests\Feature\Content;

use App\Modules\Content\Support\LegalPdf;
use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LegalPdfTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SettingsSeeder::class);
        Storage::fake('local');
    }

    public function test_the_withdrawal_form_is_addressed_to_the_seller_and_names_the_order(): void
    {
        foreach (['company_name' => 'MellowAura Katarzyna Samborska', 'company_address' => 'ul. Wirtualna 1, 30-001 Kraków', 'contact_email' => 'kasia@example.com'] as $key => $value) {
            Setting::query()->where('key', $key)->update(['value' => json_encode($value)]);
        }
        app(Settings::class)->refresh();
        $pdf = app(LegalPdf::class);

        $html = $pdf->withdrawalFormHtml('MA-2026-1047', Carbon::parse('2026-11-20'));

        $this->assertStringContainsString('Twoje zamówienie: <strong>MA-2026-1047</strong>, złożone 20 listopada 2026', $html);
        $this->assertStringContainsString('<div class="filled">MellowAura Katarzyna Samborska, ul. Wirtualna 1, 30-001 Kraków, kasia@example.com</div>', $html);
        $this->assertStringContainsString('o moim/naszym odstąpieniu od umowy sprzedaży następujących towarów(*)', $html);
        $this->assertStringContainsString('Podpis konsumenta(-ów) (tylko jeżeli formularz jest przesyłany w wersji papierowej)', $html);
        $this->assertStringContainsString(e(route('withdrawal.create', ['zamowienie' => 'MA-2026-1047'])), $html);

        $document = $pdf->withdrawalForm('MA-2026-1047');
        $this->assertStringStartsWith('%PDF-', $document);
        $this->assertSame(1, preg_match_all('#/Type\s*/Page[^s]#', $document), 'The form fits on one A4 page.');
    }

    public function test_the_terms_pdf_is_rendered_once_per_wording(): void
    {
        $pdf = app(LegalPdf::class);

        $this->assertStringContainsString('Regulamin sklepu internetowego MellowAura', $pdf->termsHtml());
        $this->assertStringContainsString('Wersja 0.2 z 16 września 2026', $pdf->termsHtml());
        $this->assertStringStartsWith('%PDF-', $pdf->terms());
        $this->assertCount(1, Storage::disk('local')->files('legal-pdf'));

        $pdf->terms();
        $this->assertCount(1, Storage::disk('local')->files('legal-pdf'));

        Setting::query()->where('key', 'company_nip')->update(['value' => json_encode('1111111111')]);
        app(Settings::class)->refresh();
        $this->assertStringContainsString('NIP 1111111111', $pdf->termsHtml());

        $pdf->terms();
        $this->assertCount(2, Storage::disk('local')->files('legal-pdf'));
    }
}
