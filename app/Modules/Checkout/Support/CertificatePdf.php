<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Checkout\Models\Certificate;
use App\Modules\Checkout\Models\OrderItem;
use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Pdf;
use App\Modules\Shared\Support\QrCode;
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

    /**
     * The sentences on the front that Kasia rewrites in the panel, each with the most characters and lines the card
     * holds without pushing the fields into the signature (MA-240). An empty one prints the text from lang.
     *
     * @var array<string, array{int, int}>
     */
    public const TEXTS = ['eyebrow' => [36, 1], 'quote' => [110, 4], 'brand_note' => [40, 1]];

    public function __construct(private Settings $settings) {}

    /**
     * The cards in the language the order was placed in: an English order has its piece, its care and its
     * warnings in English, and the card says so too.
     *
     * @param  Collection<int, Certificate>  $certificates
     * @param  array<string, ?string>|null  $texts
     */
    public function render(Collection $certificates, string $locale = 'pl', ?array $texts = null): string
    {
        return Pdf::render($this->html($certificates, $locale, $texts), 'portrait', 'a6');
    }

    /**
     * @param  Collection<int, Certificate>  $certificates
     * @param  array<string, ?string>|null  $texts  typed in the panel and not saved yet; null reads the settings
     */
    public function html(Collection $certificates, string $locale = 'pl', ?array $texts = null): string
    {
        return Locales::within($locale, fn () => $this->view($certificates, $texts));
    }

    /**
     * One card for a sample piece, so the panel shows the texts on paper before they are saved.
     *
     * @param  array<string, ?string>  $texts
     */
    public function sample(string $locale, array $texts): string
    {
        $certificate = Locales::within($locale, fn () => (new Certificate(['piece' => 1, 'number' => now()->format('Y').'/0001']))
            ->setRelation('orderItem', (new OrderItem)->forceFill([
                // Any token of the right shape: the preview shows where the code goes, not a page that exists.
                'page_token' => str_repeat('a', PieceToken::LENGTH),
                'product_name' => __('checkout::certificate.sample_work'),
                'variant_label' => '',
                'custom_text' => __('checkout::certificate.sample_text'),
            ])->setRelation('variant', null)));

        return $this->render(collect([$certificate]), $locale, $texts);
    }

    /** The setting holding a text of the card: no suffix in Polish, `_en` in English, like the other texts. */
    public static function setting(string $text, string $locale): string
    {
        return 'certificate_'.$text.($locale === Locales::default() ? '' : '_'.$locale);
    }

    /**
     * @param  Collection<int, Certificate>  $certificates
     * @param  array<string, ?string>|null  $texts
     */
    private function view(Collection $certificates, ?array $texts): string
    {
        return view('checkout::pdf.certificates', [
            'cards' => $certificates->map($this->card(...))->values(),
            'texts' => collect(self::TEXTS)->map(function (array $limits, string $text) use ($texts) {
                $value = $texts === null ? $this->settings->get(self::setting($text, Locales::current())) : ($texts[$text] ?? null);

                return filled($value) ? $value : __('checkout::certificate.'.$text);
            })->all(),
            'fonts' => Pdf::fonts(),
            'website' => self::WEBSITE,
            'producer' => collect(['company_name', 'company_address', 'contact_email'])->map(fn (string $key) => $this->settings->get($key))->filter()->values(),
            'contact' => collect([
                $this->settings->get('contact_phone'),
                ($handle = $this->settings->get('instagram_handle')) ? '@'.ltrim($handle, '@') : null,
            ])->filter()->values(),
        ])->render();
    }

    public static function filename(string $orderNumber, string $locale = 'pl'): string
    {
        return Locales::within($locale, fn () => __('checkout::certificate.filename')).'-'.$orderNumber.'.pdf';
    }

    /**
     * @return array{number: string, work: string, text: ?string, ceramics: bool, fired: ?string, glaze: ?string, dimensions: ?string, qr: ?string, notes: list<array{string, string}>}
     */
    private function card(Certificate $certificate): array
    {
        $item = $certificate->orderItem;
        $product = $item->variant?->product;
        // A mug from the configurator has no product in the shop, but it is ceramics too.
        $ceramics = $product === null || $product->category?->group === CategoryGroup::Ceramics;
        // The general care rule and the panel's wording of food contact are Polish; another language reads the
        // product's own care line and the shop's label for food contact (see the product page).
        $polish = Locales::current() === Locales::default();

        return [
            'number' => (string) $certificate->number,
            'work' => collect([$item->pieceName(), $item->variant_label])->filter()->join(' · '),
            'text' => $item->custom_text === null ? null : str_replace("\n", ' / ', $item->custom_text),
            'ceramics' => $ceramics,
            // Filled in the panel (MA-234); left empty they stay lines to write on by hand.
            'fired' => $item->fired_on?->format('j.m.Y'),
            'glaze' => $item->glaze,
            'dimensions' => $product ? (collect($product->dimensionLabels())->map(fn (string $value, string $label) => $label.' '.$value)->join(' · ') ?: null) : null,
            // The same page as the card in the parcel (MA-234).
            'qr' => $item->page_token === null ? null : QrCode::dataUri($item->pageUrl()),
            'notes' => collect([
                [__('checkout::certificate.care'), $product?->care_note ?: ($ceramics && $polish ? $this->settings->get('care_rule_ceramics') : null)],
                [__('checkout::certificate.food_contact'), $polish ? $product?->food_contact?->option() : $product?->food_contact?->label()],
                [__('checkout::certificate.deviation'), $item->accepted_deviation ?? $product?->deviation],
                [__('checkout::certificate.warnings'), $product?->safety_warnings],
            ])->filter(fn (array $note) => filled($note[1]))->values()->all(),
        ];
    }
}
