<?php

namespace App\Modules\Checkout\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Checkout\Http\Requests\Admin\SaveCertificateTextsRequest;
use App\Modules\Checkout\Support\CertificatePdf;
use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Settings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/**
 * „Certyfikat unikatu” in the panel: the heading, the quote and the line under the logo on the front of the card,
 * in each language. „Podgląd” prints a sample card from what is typed, without saving it (MA-240).
 */
class CertificateTextsController extends Controller
{
    public function edit(Settings $settings): View
    {
        return view('checkout::admin.certificate.edit', [
            'locales' => Locales::enabled(),
            'values' => collect(SaveCertificateTextsRequest::fields())->mapWithKeys(fn (array $key) => [$key['setting'] => $settings->get($key['setting'])])->all(),
            'defaults' => collect(SaveCertificateTextsRequest::fields())
                ->mapWithKeys(fn (array $key) => [$key['setting'] => Locales::within($key['locale'], fn () => __('checkout::certificate.'.$key['text']))])
                ->all(),
        ]);
    }

    public function update(SaveCertificateTextsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.certificate.edit')->withFragment('certyfikat')->with('panel_status', 'Zapisane. Certyfikaty drukują się już z tymi tekstami.');
    }

    public function preview(SaveCertificateTextsRequest $request, CertificatePdf $pdf): Response
    {
        $locale = in_array($typed = $request->input('preview'), Locales::enabled(), true) ? $typed : Locales::default();

        return response($pdf->sample($locale, $request->texts($locale)), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.CertificatePdf::filename('podglad', $locale).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
