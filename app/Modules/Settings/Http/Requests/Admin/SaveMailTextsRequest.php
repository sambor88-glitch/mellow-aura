<?php

namespace App\Modules\Settings\Http\Requests\Admin;

use App\Modules\Localization\Support\Locales;
use App\Modules\Shared\Mail\MailTexts;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The sentences of customer mails Kasia writes in the panel, in every language a mail has a template in.
 * Plain text: a line break stays a line break, anything else goes out as typed. An empty field sends the default.
 */
class SaveMailTextsRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'maile';

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
            ->mapWithKeys(fn (array $field) => [$field['setting'] => [
                'nullable',
                'string',
                'max:'.$field['max'],
                function (string $attribute, mixed $value, Closure $fail) use ($field) {
                    if (! is_string($value)) {
                        return;
                    }

                    if (count(preg_split('/\R/', trim($value))) > $field['lines']) {
                        $fail($field['lines'] === 1 ? 'Tu mieści się jedna linijka — bez Entera' : 'Najwyżej '.$field['lines'].' linijki — połącz kilka w jedną');

                        return;
                    }

                    preg_match_all('/\{([^}]*)\}/', $value, $found);
                    $unknown = array_diff($found[1], array_keys($field['placeholders']));

                    if ($unknown !== []) {
                        $fail($field['placeholders'] === []
                            ? 'Nie znam {'.reset($unknown).'} — tu nic nie wstawiam z zamówienia'
                            : 'Nie znam {'.reset($unknown).'} — możesz wstawić: '.collect($field['placeholders'])->keys()->map(fn (string $name) => '{'.$name.'}')->join(', '));
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
            '*.max' => 'Zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array<string, ?string>
     */
    public function settings(): array
    {
        return collect(self::fields())
            ->mapWithKeys(fn (array $field) => [$field['setting'] => filled($value = $this->validated($field['setting'])) ? str_replace("\r\n", "\n", trim($value)) : null])
            ->all();
    }

    /**
     * Every field on the screen: the signature and the footer per language, then each mail's sentences in the
     * languages it is written in.
     *
     * @return list<array{setting: string, part: string, locale: string, mail: ?string, max: int, lines: int, placeholders: array<string, string>}>
     */
    public static function fields(): array
    {
        $fields = [];

        foreach (Locales::enabled() as $locale) {
            foreach (MailTexts::COMMON as $part => [$max, $lines]) {
                $fields[] = ['setting' => MailTexts::setting($part, $locale), 'part' => $part, 'locale' => $locale, 'mail' => null, 'max' => $max, 'lines' => $lines, 'placeholders' => []];
            }
        }

        foreach (MailTexts::mails() as $mail) {
            foreach (array_intersect($mail->locales, Locales::enabled()) as $locale) {
                foreach ($mail->parts as $part) {
                    [$max, $lines] = MailTexts::PARTS[$part];
                    $fields[] = ['setting' => MailTexts::setting($part, $locale, $mail->key), 'part' => $part, 'locale' => $locale, 'mail' => $mail->key, 'max' => $max, 'lines' => $lines, 'placeholders' => $mail->placeholders];
                }
            }
        }

        return $fields;
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.mail-texts.edit').'#maile';
    }
}
