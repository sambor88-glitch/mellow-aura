<?php

namespace App\Modules\Checkout\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A new BLIK code for the same order (BlikController). It goes to the gateway and is never saved or flashed.
 */
class RetryBlikRequest extends FormRequest
{
    /** @var list<string> */
    protected $dontFlash = ['blik_code'];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['blik_code' => ['required', 'digits:6']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'blik_code.required' => __('checkout::validation.blik_code'),
            'blik_code.digits' => __('checkout::validation.blik_code'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['blik_code' => preg_replace('/\D+/', '', (string) $this->input('blik_code'))]);
    }
}
