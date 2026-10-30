<?php

namespace App\Modules\Settings\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Actions\SaveSettings;
use App\Modules\Settings\Http\Requests\Admin\SaveMailTextsRequest;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Mail\MailTexts;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * „Maile” in the panel: the subject, the opening and the closing sentence of each customer mail, and the signature
 * and footer they share (MA-239), and the picture above the signature, which MailSignatureImageController saves (MA-287).
 * „Podgląd” shows a mail for a made-up order with what is typed, without saving it.
 * Mails to Kasia keep their texts in code.
 */
class MailTextsController extends Controller
{
    public function edit(Settings $settings, MailTexts $texts): View
    {
        $fields = SaveMailTextsRequest::fields();

        return view('settings::admin.mail-texts.edit', [
            'locales' => Locales::enabled(),
            'mails' => MailTexts::mails(),
            'fields' => collect($fields)->keyBy('setting')->all(),
            'signatureImage' => $texts->signatureImage(),
            'signature' => $texts->common('signature'),
            'values' => collect($fields)->mapWithKeys(fn (array $field) => [$field['setting'] => $settings->get($field['setting'])])->all(),
            'defaults' => collect($fields)
                ->mapWithKeys(fn (array $field) => [$field['setting'] => Locales::within($field['locale'], fn () => $texts->default($field['part'], $field['mail']))])
                ->all(),
        ]);
    }

    public function update(SaveMailTextsRequest $request, SaveSettings $saveSettings): RedirectResponse
    {
        $saveSettings($request->settings());

        return to_route('admin.mail-texts.edit')->withFragment('maile')->with('panel_status', 'Zapisane. Maile wychodzą już z tymi tekstami.');
    }

    public function preview(SaveMailTextsRequest $request, MailTexts $texts): View
    {
        [$key, $locale] = explode(':', (string) $request->input('preview')) + [1 => Locales::default()];
        $class = MailTexts::mailClass($key);
        abort_if($class === null, 404);

        $locale = in_array($locale, array_intersect($class::editableTexts()->locales, Locales::enabled()), true) ? $locale : Locales::default();
        $mail = $class::sample($locale)->locale($locale);

        [$subject, $html] = $texts->previewing($request->settings(), fn () => [
            Locales::within($locale, fn () => $mail->envelope()->subject),
            $mail->render(),
        ]);

        return view('settings::admin.mail-texts.preview', [
            'label' => $class::editableTexts()->label,
            'locale' => $locale,
            'subject' => $subject,
            'html' => $html,
        ]);
    }
}
