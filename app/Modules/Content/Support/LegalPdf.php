<?php

namespace App\Modules\Content\Support;

use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Pdf;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/**
 * The documents the order confirmation carries on a durable medium: the terms of sale as a PDF
 * and the model withdrawal form (annex 1 to the terms) addressed to the seller from the panel.
 */
class LegalPdf
{
    public function __construct(private Settings $settings) {}

    /**
     * Rendered once per wording: a change in the text or in the seller's details makes a new file.
     */
    public function terms(): string
    {
        $html = $this->termsHtml();
        // The fonts path changes with every release on the server, so it stays out of the name.
        $path = 'legal-pdf/terms-'.sha1(str_replace(Pdf::fonts(), '', $html)).'.pdf';

        if (Storage::disk('local')->exists($path)) {
            return (string) Storage::disk('local')->get($path);
        }

        $pdf = Pdf::render($html);
        Storage::disk('local')->put($path, $pdf);

        return $pdf;
    }

    public function termsHtml(): string
    {
        $document = LegalDocument::terms();

        return view('content::pdf.document', [
            'document' => $document,
            'html' => $document->html($this->settings),
            'fonts' => Pdf::fonts(),
        ])->render();
    }

    public function withdrawalForm(?string $orderNumber = null, ?CarbonInterface $orderedAt = null): string
    {
        return Pdf::render($this->withdrawalFormHtml($orderNumber, $orderedAt));
    }

    public function withdrawalFormHtml(?string $orderNumber = null, ?CarbonInterface $orderedAt = null): string
    {
        return view('content::pdf.withdrawal-form', [
            'addressee' => collect(['company_name', 'company_address', 'contact_email'])
                ->map(fn (string $key) => $this->settings->get($key))
                ->filter()
                ->join(', ') ?: 'MellowAura',
            'orderNumber' => $orderNumber,
            'orderedAt' => $orderedAt,
            'contactEmail' => $this->settings->get('contact_email'),
            'onlineForm' => Route::has('withdrawal.create') ? route('withdrawal.create', array_filter(['zamowienie' => $orderNumber])) : null,
            'fonts' => Pdf::fonts(),
        ])->render();
    }
}
