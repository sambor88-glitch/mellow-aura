<?php

namespace App\Modules\Gifts\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * How long a voucher is valid, how much fits on it, the sentence on the PDF about using it and the one
 * under the heading of the vouchers page.
 * The name limit stops at 60, because the voucher keeps no more.
 */
class SaveVoucherSettingsRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'vouchery';

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
            'voucher_validity_months' => ['required', 'integer', 'min:1', 'max:60'],
            'voucher_recipient_name_max_chars' => ['required', 'integer', 'min:10', 'max:60'],
            'voucher_dedication_max_chars' => ['required', 'integer', 'min:20', 'max:500'],
            'text_voucher_how_to_use' => ['nullable', 'string', 'max:400'],
            'text_vouchers_lead' => ['nullable', 'string', 'max:400'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'voucher_validity_months.*' => 'Wpisz liczbę miesięcy od 1 do 60',
            'voucher_recipient_name_max_chars.*' => 'Wpisz liczbę znaków od 10 do 60',
            'voucher_dedication_max_chars.*' => 'Wpisz liczbę znaków od 20 do 500',
            'text_voucher_how_to_use.max' => 'Ten tekst zmieszczę do :max znaków',
            'text_vouchers_lead.max' => 'Ten tekst zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array<string, int|string|null>
     */
    public function settings(): array
    {
        return [
            'voucher_validity_months' => (int) $this->validated('voucher_validity_months'),
            'voucher_recipient_name_max_chars' => (int) $this->validated('voucher_recipient_name_max_chars'),
            'voucher_dedication_max_chars' => (int) $this->validated('voucher_dedication_max_chars'),
            'text_voucher_how_to_use' => filled($text = $this->validated('text_voucher_how_to_use')) ? trim($text) : null,
            'text_vouchers_lead' => filled($lead = $this->validated('text_vouchers_lead')) ? str_replace("\r\n", "\n", trim($lead)) : null,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.gifts.edit').'#vouchery';
    }
}
