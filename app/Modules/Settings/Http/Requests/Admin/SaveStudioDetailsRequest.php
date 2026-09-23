<?php

namespace App\Modules\Settings\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * The „Dane pracowni” card. Contacts and profiles show in the footer and in the data for Google.
 * The studio's address and the parcel locker code never show there — the address goes only into
 * the e-mail after a workshop booking.
 */
class SaveStudioDetailsRequest extends FormRequest
{
    public const KEYS = [
        'contact_phone', 'contact_email', 'instagram_handle', 'facebook_url', 'google_business_profile_url',
        'location_description', 'studio_address', 'parcel_locker_code', 'footer_city',
    ];

    /** @var string */
    protected $errorBag = 'pracownia';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $phone = function (string $attribute, mixed $value, Closure $fail): void {
            $digits = strlen((string) preg_replace('/\D+/', '', (string) $value));

            if (! preg_match('/^\+?[\d\s()-]+$/', (string) $value) || $digits < 9 || $digits > 15) {
                $fail('Wpisz numer telefonu, np. 600 100 200');
            }
        };

        return [
            'contact_phone' => ['nullable', 'string', $phone],
            'contact_email' => ['nullable', 'email:rfc', 'max:120'],
            'instagram_handle' => ['nullable', 'regex:/^[A-Za-z0-9._]{1,30}$/'],
            'facebook_url' => ['nullable', 'url:https', 'max:255'],
            'google_business_profile_url' => ['nullable', 'url:https', 'max:255'],
            'location_description' => ['nullable', 'string', 'max:80'],
            'studio_address' => ['nullable', 'string', 'max:160'],
            'parcel_locker_code' => ['nullable', 'regex:/^[A-Z0-9-]{4,12}$/'],
            'footer_city' => ['nullable', 'string', 'max:60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $link = 'Wklej cały adres, zaczynający się od https://';

        return [
            'contact_email.email' => 'Wpisz cały adres e-mail — z małpą i domeną',
            'contact_email.max' => 'Adres e-mail zmieszczę do :max znaków',
            'instagram_handle.regex' => 'Wpisz samą nazwę profilu, np. mellowaura',
            'facebook_url.url' => $link,
            'facebook_url.max' => 'Adres zmieszczę do :max znaków',
            'google_business_profile_url.url' => $link,
            'google_business_profile_url.max' => 'Adres zmieszczę do :max znaków',
            'location_description.max' => 'Opis okolicy zmieszczę do :max znaków',
            'studio_address.max' => 'Adres zmieszczę do :max znaków',
            'parcel_locker_code.regex' => 'Wpisz kod Paczkomatu z aplikacji InPost, np. KRA01M',
            'footer_city.max' => 'Miasto zmieszczę do :max znaków',
        ];
    }

    /**
     * The card's values to save; an empty field is saved as empty and does not show on the site.
     *
     * @return array<string, ?string>
     */
    public function settings(): array
    {
        $values = array_map(
            fn (mixed $value) => filled($value) ? trim((string) $value) : null,
            Arr::only($this->validated(), self::KEYS),
        );

        if (isset($values['contact_phone'])) {
            $values['contact_phone'] = self::phone($values['contact_phone']);
        }

        return $values;
    }

    /**
     * Pasted links and profile addresses become what the site needs: a bare Instagram name,
     * a link with https:// and a locker code in capitals without spaces.
     */
    protected function prepareForValidation(): void
    {
        $instagram = Str::of((string) $this->input('instagram_handle'))
            ->before('?')
            ->replaceMatches('~^(https?://)?(www\.)?instagram\.com/~i', '')
            ->trim('@/ ')
            ->toString();

        $this->merge([
            'instagram_handle' => $instagram === '' ? null : $instagram,
            'facebook_url' => self::withScheme($this->input('facebook_url')),
            'google_business_profile_url' => self::withScheme($this->input('google_business_profile_url')),
            'parcel_locker_code' => filled($this->input('parcel_locker_code'))
                ? Str::upper((string) preg_replace('/\s+/', '', (string) $this->input('parcel_locker_code')))
                : null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.settings.edit').'#pracownia';
    }

    /**
     * A Polish number without the country code gets +48, so the WhatsApp link and the data for Google work.
     */
    private static function phone(string $phone): string
    {
        $digits = (string) preg_replace(['/\D+/', '/^00/'], '', $phone);

        if (strlen($digits) === 9) {
            $digits = '48'.$digits;
        }

        return strlen($digits) === 11 && str_starts_with($digits, '48')
            ? '+48 '.implode(' ', str_split(substr($digits, 2), 3))
            : '+'.$digits;
    }

    private static function withScheme(mixed $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        return preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) ? $url : 'https://'.$url;
    }
}
