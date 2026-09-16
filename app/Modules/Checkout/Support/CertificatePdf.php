<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Checkout\Models\Certificate;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Pdf;
use Illuminate\Support\Collection;

/**
 * Certificates of uniqueness as A6 cards, laid out like "Certyfikat unikatu.dc.html": the front with the piece
 * and its number, lines for the firing date and the signature by hand; the back with care, warnings and the
 * producer's details. The card goes into the parcel, which is where the product safety rules (GPSR) want those
 * details for a product sold online. Every fact comes from the product and the settings in the panel.
 */
class CertificatePdf
{
    /** The shop's address as printed, whatever server renders the card. */
    private const WEBSITE = 'mellow-aura.com';

    public function __construct(private Settings $settings) {}

    /**
     * @param  Collection<int, Certificate>  $certificates
     */
    public function render(Collection $certificates): string
    {
        return Pdf::render($this->html($certificates), 'portrait', 'a6');
    }

    /**
     * @param  Collection<int, Certificate>  $certificates
     */
    public function html(Collection $certificates): string
    {
        return view('checkout::pdf.certificates', [
            'cards' => $certificates->map($this->card(...))->values(),
            'fonts' => Pdf::fonts(),
            'website' => self::WEBSITE,
            'producer' => collect(['company_name', 'company_address', 'contact_email'])->map(fn (string $key) => $this->settings->get($key))->filter()->values(),
            'contact' => collect([
                $this->settings->get('contact_phone'),
                ($handle = $this->settings->get('instagram_handle')) ? '@'.ltrim($handle, '@') : null,
            ])->filter()->values(),
        ])->render();
    }

    public static function filename(string $orderNumber): string
    {
        return 'certyfikaty-'.$orderNumber.'.pdf';
    }

    /**
     * @return array{number: string, work: string, text: ?string, ceramics: bool, dimensions: ?string, notes: list<array{string, string}>}
     */
    private function card(Certificate $certificate): array
    {
        $item = $certificate->orderItem;
        $product = $item->variant?->product;
        // A mug from the configurator has no product in the shop, but it is ceramics too.
        $ceramics = $product === null || $product->category?->group === CategoryGroup::Ceramics;

        return [
            'number' => (string) $certificate->number,
            'work' => collect([$item->product_name, $item->variant_label])->filter()->join(' · '),
            'text' => $item->custom_text === null ? null : str_replace("\n", ' / ', $item->custom_text),
            'ceramics' => $ceramics,
            'dimensions' => $product ? (collect($product->dimensionLabels())->map(fn (string $value, string $label) => $label.' '.$value)->join(' · ') ?: null) : null,
            'notes' => collect([
                ['Pielęgnacja', $product?->care_note ?: ($ceramics ? $this->settings->get('care_rule_ceramics') : null)],
                ['Kontakt z żywnością', $product?->food_contact?->option()],
                ['Zwróć uwagę', $item->accepted_deviation ?? $product?->deviation],
                ['Ostrzeżenia', $product?->safety_warnings],
            ])->filter(fn (array $note) => filled($note[1]))->values()->all(),
        ];
    }
}
