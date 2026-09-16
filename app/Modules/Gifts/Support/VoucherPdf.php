<?php

namespace App\Modules\Gifts\Support;

use App\Modules\Gifts\Models\Voucher;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Pdf;

/**
 * The voucher as an A4 landscape page, laid out like "Voucher warsztatowy.dc.html" in the site's typefaces.
 * The workshop description comes from the panel for the voucher's product; the WhatsApp number is the same
 * one the rest of the site shows.
 */
class VoucherPdf
{
    /** The shop's address as printed, whatever server renders the voucher. */
    private const WEBSITE = 'www.mellow-aura.com';

    public function __construct(private Settings $settings) {}

    public function render(Voucher $voucher): string
    {
        return Pdf::render($this->html($voucher), 'landscape');
    }

    public function html(Voucher $voucher): string
    {
        $voucher->loadMissing('orderItem.variant.product.variants');
        $notes = (array) $this->settings->get('voucher_workshop_notes', []);
        $item = $voucher->orderItem;
        $product = $item?->variant?->product;

        return view('gifts::pdf.voucher', [
            'voucher' => $voucher,
            'kind' => collect([$item?->product_name ?? 'voucher', $this->showsValue($voucher) ? $item->variant_label : null])->filter()->join(' · '),
            'fonts' => Pdf::fonts(),
            'notes' => is_array($notes[$product?->slug] ?? null) ? $notes[$product->slug] : [],
            'howToUse' => $this->settings->get('text_voucher_how_to_use'),
            'whatsApp' => $this->settings->get('contact_phone'),
            'contact' => array_values(array_filter([
                $this->settings->get('contact_email'),
                self::WEBSITE,
                ($handle = $this->settings->get('instagram_handle')) ? '@'.ltrim($handle, '@') : null,
            ])),
        ])->render();
    }

    /**
     * A size that changes the price says what the voucher is worth — „250 zł”, „Dla dwóch osób” — so it is printed.
     * Sizes at one price only say how the voucher is delivered („PDF do wydruku”), which the printout doesn't need.
     */
    private function showsValue(Voucher $voucher): bool
    {
        $variants = $voucher->orderItem?->variant?->product?->variants;

        return filled($voucher->orderItem?->variant_label) && $variants !== null && $variants->pluck('price_gross')->unique()->count() > 1;
    }

    public static function filename(Voucher $voucher): string
    {
        return 'voucher-'.$voucher->code.'.pdf';
    }
}
