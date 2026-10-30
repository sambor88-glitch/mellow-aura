<?php

namespace App\Modules\Settings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Actions\ReplaceMailSignatureImage;
use App\Modules\Settings\Http\Requests\Admin\StoreMailSignatureImageRequest;
use Illuminate\Http\RedirectResponse;

/**
 * The picture above the signature on „Maile” (MA-287). It saves on its own, apart from the texts,
 * and goes into every customer mail sent from then on.
 */
class MailSignatureImageController extends Controller
{
    public function store(StoreMailSignatureImageRequest $request, ReplaceMailSignatureImage $replace): RedirectResponse
    {
        $replace($request->file('image'));

        return $this->saved('Grafika wgrana. Jest już w każdym mailu do klientów.');
    }

    public function destroy(ReplaceMailSignatureImage $replace): RedirectResponse
    {
        $replace(null);

        return $this->saved('Grafika zdjęta. Maile mają znowu sam podpis.');
    }

    private function saved(string $status): RedirectResponse
    {
        return to_route('admin.mail-texts.edit')->withFragment('grafika-podpisu')->with('panel_status', $status);
    }
}
