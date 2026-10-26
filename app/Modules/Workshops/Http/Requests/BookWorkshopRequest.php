<?php

namespace App\Modules\Workshops\Http\Requests;

use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Workshops\Http\Controllers\BookingController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The booking form: three fields for contact, how many seats, how to pay and accepting the terms.
 * Whether the seats are still free is counted by PlaceBooking, under the lock.
 */
class BookWorkshopRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(\+?48)?[\s-]*(\d[\s-]*){9}$/'],
            'seats' => ['required', 'integer', 'min:1', 'max:'.BookingController::MAX_SEATS],
            'payment_method' => ['required', Rule::in(array_column(BookingController::paymentMethods(), 'value'))],
            // Without BLIK Level 0 the code is typed on Przelewy24's page, not here.
            'blik_code' => $this->container->make(PaymentGateway::class)->takesBlikCode()
                ? ['exclude_unless:payment_method,'.PaymentMethod::Blik->value, 'required', 'digits:6']
                : ['exclude'],
            'expected_total' => ['required', 'integer'],
            'accept_terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Wpisz imię i nazwisko',
            'name.max' => 'Imię i nazwisko zmieści się w 120 znakach',
            'email.required' => 'Wpisz e-mail — tu przyjdzie potwierdzenie zapisu',
            'email.email' => 'Sprawdź e-mail — brakuje w nim @ albo domeny',
            'email.max' => 'E-mail jest za długi',
            'phone.required' => 'Wpisz telefon — zadzwonię, gdyby coś się zmieniło',
            'phone.regex' => 'Wpisz 9 cyfr numeru, np. 600 100 200',
            'seats.required' => 'Wybierz, ile miejsc rezerwujesz',
            'seats.integer' => 'Wybierz, ile miejsc rezerwujesz',
            'seats.min' => 'Wybierz co najmniej jedno miejsce',
            'seats.max' => 'Przez stronę zarezerwujesz do :max miejsc — przy większej grupie napisz do mnie',
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
