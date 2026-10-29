<?php

namespace App\Modules\Catalog\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * „Napisz do mnie, gdy wróci” under a sold-out size (MA-266): the size, the e-mail and the consent to this one mail.
 * The „website” field is hidden from people; a form that fills it in comes from a bot.
 */
class SubscribeToRestockRequest extends FormRequest
{
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
            'variant_id' => ['required', 'integer'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'consent' => ['accepted'],
            'website' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.required' => __('catalog::restock.error_email_required'),
            'email.email' => __('catalog::restock.error_email_format'),
            'email.max' => __('catalog::restock.error_email_long'),
            'consent.accepted' => __('catalog::restock.error_consent'),
        ];
    }

    public function isFromBot(): bool
    {
        return filled($this->input('website'));
    }

    protected function getRedirectUrl(): string
    {
        return url()->previous().'#powiadom';
    }
}
