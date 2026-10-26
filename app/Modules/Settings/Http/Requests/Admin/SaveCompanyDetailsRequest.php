<?php

namespace App\Modules\Settings\Http\Requests\Admin;

use App\Modules\Shared\Support\Nip;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * The „Dane firmy” card: the seller as the terms of sale, the privacy policy and the footer name it,
 * and what a payment operator checks on the site. The studio's own address is never asked for here.
 */
class SaveCompanyDetailsRequest extends FormRequest
{
    public const KEYS = [
        'company_name', 'company_nip', 'company_regon', 'company_address', 'return_address',
        'company_bank_account', 'company_vat_note', 'payment_operator',
    ];

    /** @var string */
    protected $errorBag = 'firma';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['nullable', 'string', 'max:160'],
            'company_nip' => ['nullable', function (string $attribute, mixed $value, Closure $fail): void {
                if (! Nip::isValid($value)) {
                    $fail('Ten NIP się nie zgadza — sprawdź cyfry z CEIDG');
                }
            }],
            'company_regon' => ['nullable', 'regex:/^(\d{9}|\d{14})$/'],
            'company_address' => ['nullable', 'string', 'max:200'],
            'return_address' => ['nullable', 'string', 'max:200'],
            'company_bank_account' => ['nullable', 'regex:/^\d{26}$/'],
            'company_vat_note' => ['nullable', 'string', 'max:300'],
            'payment_operator' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'company_name.max' => 'Nazwę firmy zmieszczę do :max znaków',
            'company_regon.regex' => 'REGON ma 9 albo 14 cyfr',
            'company_address.max' => 'Adres zmieszczę do :max znaków',
            'return_address.max' => 'Adres zmieszczę do :max znaków',
            'company_bank_account.regex' => 'Numer rachunku ma 26 cyfr — sprawdź, czy żadna nie uciekła',
            'company_vat_note.max' => 'Tę informację zmieszczę do :max znaków',
            'payment_operator.max' => 'Nazwę zmieszczę do :max znaków',
        ];
    }

    /**
     * An empty field is saved as empty and does not show on the site. The account number is kept in the
     * grouping people read aloud: 12 3456 7890 …
     *
     * @return array<string, ?string>
     */
    public function settings(): array
    {
        $values = array_map(
            fn (mixed $value) => filled($value) ? trim((string) $value) : null,
            Arr::only($this->validated(), self::KEYS),
        );

        if (isset($values['company_bank_account'])) {
            $account = $values['company_bank_account'];
            $values['company_bank_account'] = substr($account, 0, 2).' '.implode(' ', str_split(substr($account, 2), 4));
        }

        return $values;
    }

    /**
     * Numbers pasted with spaces, dashes or the PL prefix count as the same number.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'company_nip' => filled($this->input('company_nip')) ? Nip::digits($this->input('company_nip')) : null,
            'company_regon' => filled($this->input('company_regon')) ? (string) preg_replace('/\D+/', '', (string) $this->input('company_regon')) : null,
            'company_bank_account' => filled($this->input('company_bank_account'))
                ? (string) preg_replace(['/^\s*PL/i', '/\D+/'], '', (string) $this->input('company_bank_account'))
                : null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.settings.edit').'#firma';
    }
}
