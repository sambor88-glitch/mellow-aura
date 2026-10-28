<?php

namespace Tests\Feature\Checkout;

use App\Models\User;
use App\Modules\Checkout\Models\Certificate;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Checkout\Support\CertificatePdf;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CertificateTextsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_panel_shows_the_texts_with_the_defaults_in_empty_fields(): void
    {
        Setting::create(['key' => 'certificate_quote', 'value' => "Jedna taka\nna świecie."]);

        $this->actingAs(User::factory()->create())
            ->get('/panel/certyfikat')
            ->assertOk()
            ->assertSee('Certyfikat unikatu')
            ->assertSee("Jedna taka\nna świecie.", false)
            ->assertSee('placeholder="certyfikat unikatu"', false)
            ->assertSee('placeholder="ceramics · handmade · kraków"', false);
    }

    public function test_saved_texts_go_on_the_card_escaped_with_their_line_breaks_and_empty_ones_fall_back(): void
    {
        $this->actingAs(User::factory()->create())
            ->put('/panel/certyfikat', [
                'certificate_eyebrow' => '  karta <b>pracy</b> ',
                'certificate_quote' => "Jedna taka\r\nna świecie.",
                'certificate_brand_note' => '',
                'certificate_quote_en' => 'Only one of its kind.',
            ])
            ->assertRedirect('/panel/certyfikat#certyfikat');

        $this->assertSame('karta <b>pracy</b>', Setting::find('certificate_eyebrow')->value);
        $this->assertSame("Jedna taka\nna świecie.", Setting::find('certificate_quote')->value);
        $this->assertNull(Setting::find('certificate_brand_note')->value);

        $pdf = app(CertificatePdf::class);
        $polish = $pdf->html(collect([$this->certificate()]));
        $this->assertStringContainsString('karta &lt;b&gt;pracy&lt;/b&gt;', $polish);
        $this->assertStringContainsString("Jedna taka<br>\nna świecie.", $polish);
        $this->assertStringContainsString('ceramika · rękodzieło · kraków', $polish);

        $english = $pdf->html(collect([$this->certificate()]), 'en');
        $this->assertStringContainsString('Only one of its kind.', $english);
        $this->assertStringContainsString('certificate of uniqueness', $english);
        $this->assertStringNotContainsString('Jedna taka', $english);
    }

    public function test_a_text_too_long_for_the_card_is_not_saved(): void
    {
        $this->actingAs(User::factory()->create())
            ->from('/panel/certyfikat')
            ->put('/panel/certyfikat', [
                'certificate_quote' => "jeden\ndwa\ntrzy\ncztery\npięć",
                'certificate_eyebrow' => str_repeat('a', 41),
            ])
            ->assertSessionHasErrors(['certificate_quote', 'certificate_eyebrow'], errorBag: 'certyfikat');

        $this->assertSame(0, Setting::count());
    }

    public function test_the_preview_prints_a_sample_card_from_the_typed_texts_without_saving_them(): void
    {
        $this->partialMock(CertificatePdf::class, fn ($mock) => $mock->shouldReceive('sample')
            ->once()
            ->with('en', ['eyebrow' => null, 'quote' => 'Only one of its kind.', 'brand_note' => null])
            ->andReturn('%PDF-sample'));

        $this->actingAs(User::factory()->create())
            ->put('/panel/certyfikat/podglad', ['preview' => 'en', 'certificate_quote_en' => 'Only one of its kind.', 'certificate_quote' => 'Jedna taka.'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');

        $this->assertSame(0, Setting::count());
    }

    public function test_the_sample_card_is_one_a6_sheet_front_and_back_even_with_the_longest_texts(): void
    {
        $pdf = app(CertificatePdf::class)->sample('pl', [
            'eyebrow' => str_repeat('a', CertificatePdf::TEXTS['eyebrow'][0]),
            'quote' => implode("\n", array_fill(0, 4, str_repeat('ę', 27))),
            'brand_note' => str_repeat('w', CertificatePdf::TEXTS['brand_note'][0]),
        ]);

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertSame(2, preg_match_all('#/Type\s*/Page[^s]#', $pdf));
    }

    public function test_a_helper_does_not_change_the_certificate(): void
    {
        $this->actingAs(User::factory()->helper()->create())->get('/panel/certyfikat')->assertForbidden();
    }

    private function certificate(): Certificate
    {
        return (new Certificate(['piece' => 1, 'number' => '2026/0001']))
            ->setRelation('orderItem', (new OrderItem)->forceFill(['product_name' => 'Kubek', 'variant_label' => ''])->setRelation('variant', null));
    }
}
