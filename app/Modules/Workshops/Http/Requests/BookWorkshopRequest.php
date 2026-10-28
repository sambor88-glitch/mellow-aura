<?php

namespace App\Modules\Workshops\Http\Requests;

use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Gifts\Support\VoucherBalance;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Workshops\Http\Controllers\BookingController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The booking form: three fields for contact, how many seats, how to pay and accepting the terms.
 * Whether the seats are still free is counted by PlaceBooking, under the lock. With a voucher (MA-137) the code takes
 * the place of the payment and the seats come from the voucher, so the form's seats and total are left out.
 * Amount vouchers (MA-244) come as the codes the form kept and what the screen said they pay; with nothing left to pay
 * there is no BLIK code to ask for.
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
        $byVoucher = 'exclude_if:payment_method,'.PaymentMethod::Voucher->value;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(\+?48)?[\s-]*(\d[\s-]*){9}$/'],
            'seats' => [$byVoucher, 'required', 'integer', 'min:1', 'max:'.BookingController::MAX_SEATS],
            'payment_method' => ['required', Rule::in([...array_column(BookingController::paymentMethods(), 'value'), PaymentMethod::Voucher->value])],
            // Normalized by Gifts\Support\VoucherCode; a printed code is 12 characters, typed ones may carry spaces.
            'voucher_code' => ['exclude_unless:payment_method,'.PaymentMethod::Voucher->value, 'required', 'string', 'max:30'],
            // Without BLIK Level 0 the code is typed on Przelewy24's page, not here.
            'blik_code' => $this->container->make(PaymentGateway::class)->takesBlikCode()
                ? ['exclude_unless:payment_method,'.PaymentMethod::Blik->value, Rule::excludeIf($this->paidByVouchers(...)), 'required', 'digits:6']
                : ['exclude'],
            'expected_total' => [$byVoucher, 'required', 'integer'],
            'vouchers' => [$byVoucher, 'nullable', 'array', 'max:'.VoucherBalance::MAX_CODES],
            'vouchers.*' => [$byVoucher, 'string', 'max:30'],
            'expected_vouchers' => [$byVoucher, 'nullable', 'integer', 'min:0'],
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
            'voucher_code.required' => 'Wpisz kod z vouchera, np. MA-7KQ2-9XHT',
            'voucher_code.max' => 'Kod z vouchera ma 12 znaków, np. MA-7KQ2-9XHT',
            'accept_terms.accepted' => 'Zaznacz akceptację regulaminu',
        ];
    }

    /** What the screen said: the amount vouchers leave nothing to pay. */
    private function paidByVouchers(): bool
    {
        return (int) $this->input('expected_vouchers') > 0 && (int) $this->input('expected_vouchers') >= (int) $this->input('expected_total');
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['blik_code' => preg_replace('/\D+/', '', (string) $this->input('blik_code'))]);
    }
}
