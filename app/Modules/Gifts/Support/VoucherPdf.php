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
        $voucher->loadMissing('orderItem.variant.product');
        $notes = (array) $this->settings->get('voucher_workshop_notes', []);
        $slug = $voucher->orderItem?->variant?->product?->slug;

        return view('gifts::pdf.voucher', [
            'voucher' => $voucher,
            'fonts' => Pdf::fonts(),
            'notes' => is_array($notes[$slug] ?? null) ? $notes[$slug] : [],
            'howToUse' => $this->settings->get('text_voucher_how_to_use'),
            'whatsApp' => $this->settings->get('contact_phone'),
            'contact' => array_values(array_filter([
                $this->settings->get('contact_email'),
                self::WEBSITE,
                ($handle = $this->settings->get('instagram_handle')) ? '@'.ltrim($handle, '@') : null,
            ])),
        ])->render();
    }

    public static function filename(Voucher $voucher): string
    {
        return 'voucher-'.$voucher->code.'.pdf';
    }
}
