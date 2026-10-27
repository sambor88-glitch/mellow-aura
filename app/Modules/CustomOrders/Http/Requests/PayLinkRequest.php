<?php

namespace App\Modules\CustomOrders\Http\Requests;

use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\CustomOrders\Http\Controllers\PaymentController;
use App\Modules\Payments\Contracts\PaymentGateway;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The payment link's page: how to pay and accepting the terms. Her details come from the idea she sent.
 */
class PayLinkRequest extends FormRequest
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
        return [
            'payment_method' => ['required', Rule::in(array_column(PaymentController::paymentMethods(), 'value'))],
            // Without BLIK Level 0 the code is typed on Przelewy24's page, not here.
            'blik_code' => $this->container->make(PaymentGateway::class)->takesBlikCode()
                ? ['exclude_unless:payment_method,'.PaymentMethod::Blik->value, 'required', 'digits:6']
                : ['exclude'],
            'accept_terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'payment_method.required' => 'Wybierz, jak płacisz',
            'payment_method.in' => 'Wybierz, jak płacisz',
            'blik_code.required' => 'Wpisz 6 cyfr kodu BLIK z aplikacji banku',
            'blik_code.digits' => 'Wpisz 6 cyfr kodu BLIK z aplikacji banku',
            'accept_terms.accepted' => 'Zaznacz akceptację regulaminu',
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['blik_code' => preg_replace('/\D+/', '', (string) $this->input('blik_code'))]);
    }
}
