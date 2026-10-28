<?php

namespace App\Modules\Checkout\Http\Requests\Admin;

use App\Modules\Checkout\Support\CertificatePdf;
use App\Modules\Localization\Support\Locales;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The sentences on the front of the certificate, in every language the shop sells in. Plain text: a line break
 * stays a line break on the card, anything else prints as typed. An empty field prints the default text.
 */
class SaveCertificateTextsRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'certyfikat';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return collect(self::fields())
            ->mapWithKeys(fn (array $key) => [$key['setting'] => [
                'nullable',
                'string',
                'max:'.CertificatePdf::TEXTS[$key['text']][0],
                function (string $attribute, mixed $value, Closure $fail) use ($key) {
                    $lines = CertificatePdf::TEXTS[$key['text']][1];

                    if (is_string($value) && count(preg_split('/\R/', trim($value))) > $lines) {
                        $fail($lines === 1 ? 'Na karcie mieści się jedna linijka — bez Entera' : 'Na karcie mieszczą się '.$lines.' linijki — połącz kilka w jedną');
                    }
                },
            ]])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.max' => 'Zmieszczę do :max znaków — dłuższy tekst nie wejdzie na kartę',
        ];
    }

    /**
     * @return array<string, ?string>
     */
    public function settings(): array
    {
        return collect(self::fields())
            ->mapWithKeys(fn (array $key) => [$key['setting'] => $this->clean($key['setting'])])
            ->all();
    }

    /**
     * The texts of one language as typed, for the sample card.
     *
     * @return array<string, ?string>
     */
    public function texts(string $locale): array
    {
        return collect(CertificatePdf::TEXTS)
            ->map(fn (array $limits, string $text) => $this->clean(CertificatePdf::setting($text, $locale)))
            ->all();
    }

    /**
     * @return list<array{text: string, locale: string, setting: string}>
     */
    public static function fields(): array
    {
        return collect(Locales::enabled())
            ->crossJoin(array_keys(CertificatePdf::TEXTS))
            ->map(fn (array $pair) => ['text' => $pair[1], 'locale' => $pair[0], 'setting' => CertificatePdf::setting($pair[1], $pair[0])])
            ->all();
    }

    private function clean(string $setting): ?string
    {
        return filled($value = $this->validated($setting)) ? str_replace("\r\n", "\n", trim($value)) : null;
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.certificate.edit').'#certyfikat';
    }
}
