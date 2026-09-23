<?php

namespace App\Modules\Checkout\Http\Requests;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Shared\Support\Nip;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Three fields, delivery, payment and accepting the terms are required, and so is accepting each unexpected
 * feature of a product in the cart (terms §4.5). A cart of vouchers sent as PDFs has no delivery to choose.
 * The address, NIP and note are optional, but an address has to be whole and a NIP has to add up.
 */
class PlaceOrderRequest extends FormRequest
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
            'phone' => ['required', 'string', 'regex:/^(\+?48)?[\s-]*(\d[\s-]*){9}$/'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:120'],
            'street' => ['nullable', 'required_with:postal_code,city', 'string', 'max:160'],
            'postal_code' => ['nullable', 'required_with:street,city', 'regex:/^\d{2}-\d{3}$/'],
            'city' => ['nullable', 'required_with:street,postal_code', 'string', 'max:80'],
            'invoice_nip' => ['bail', 'nullable', 'digits:10', $this->checksum(...)],
            'note' => ['nullable', 'string', 'max:500'],
            // Vouchers sent as PDFs alone have nothing to deliver; the controller records them as sent by e-mail.
            'shipping_method' => $this->needsDelivery()
                ? ['required', Rule::in($this->container->make(ShippingMethods::class)->all()->keys())]
                : ['exclude'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'blik_code' => ['exclude_unless:payment_method,blik', 'required', 'digits:6'],
            'expected_total' => ['required', 'integer'],
            'accept_terms' => ['accepted'],
            ...$this->deviationKeys()->mapWithKeys(fn (string $key) => ['accept_deviations.'.$key => ['accepted']])->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.required' => $this->needsDelivery() ? 'Bez numeru telefonu kurier nie znajdzie paczkomatu' : 'Wpisz numer telefonu — zadzwonię tylko w sprawie zamówienia',
            'phone.regex' => 'Numer telefonu ma 9 cyfr — sprawdź, czy żadna nie uciekła',
            'email.required' => 'Wpisz e-mail — wyślę na niego potwierdzenie',
            'email.email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
            'email.max' => 'Ten adres e-mail jest za długi — sprawdź go jeszcze raz',
            'name.required' => 'Wpisz imię i nazwisko — tak podpiszę paczkę',
            'name.max' => 'Imię i nazwisko zmieszczę do :max znaków',
            'street.required_with' => 'Dopisz ulicę i numer — bez nich kurier nie trafi',
            'street.max' => 'Ulicę i numer zmieszczę do :max znaków',
            'postal_code.required_with' => 'Dopisz kod pocztowy',
            'postal_code.regex' => 'Kod pocztowy wpisz jak na kopercie, np. 30-001',
            'city.required_with' => 'Dopisz miasto',
            'city.max' => 'Nazwę miasta zmieszczę do :max znaków',
            'invoice_nip.digits' => 'NIP ma 10 cyfr — sprawdź, czy żadna nie uciekła',
            'note.max' => 'Dopisek zmieszczę do :max znaków',
            'shipping_method.required' => 'Wybierz, jak mam wysłać paczkę',
            'shipping_method.in' => 'Wybierz, jak mam wysłać paczkę',
            'payment_method.required' => 'Wybierz, jak chcesz zapłacić',
            'payment_method.enum' => 'Wybierz, jak chcesz zapłacić',
            'blik_code.required' => 'Wpisz 6-cyfrowy kod z aplikacji banku',
            'blik_code.digits' => 'Wpisz 6-cyfrowy kod z aplikacji banku',
            'accept_terms.accepted' => 'Zaznacz akceptację regulaminu — bez niej nie mogę przyjąć zamówienia',
            ...$this->deviationKeys()->mapWithKeys(fn (string $key) => [
                'accept_deviations.'.$key.'.accepted' => 'Zaznacz, że akceptujesz tę cechę — bez tego nie mogę przyjąć zamówienia',
            ])->all(),
        ];
    }

    private function needsDelivery(): bool
    {
        return $this->container->make(Cart::class)->needsDelivery();
    }

    /**
     * The cart lines whose pieces have a feature to accept.
     *
     * @return Collection<int, string>
     */
    private function deviationKeys(): Collection
    {
        return $this->container->make(Cart::class)->lines()
            ->filter(fn (CartLine $line) => $line->deviations() !== [])
            ->keys()
            ->values();
    }

    protected function prepareForValidation(): void
    {
        $postalCode = (string) $this->input('postal_code');

        $this->merge([
            'blik_code' => preg_replace('/\D+/', '', (string) $this->input('blik_code')),
            'invoice_nip' => preg_replace('/\D+/', '', (string) $this->input('invoice_nip')) ?: null,
            // The iPhone's number pad has no dash, so „30001” and „30 001” count as 30-001.
            'postal_code' => preg_match('/^\s*(\d{2})[\s-]*(\d{3})\s*$/', $postalCode, $digits) ? $digits[1].'-'.$digits[2] : $this->input('postal_code'),
        ]);
    }

    private function checksum(string $attribute, mixed $value, Closure $fail): void
    {
        if (! Nip::isValid($value)) {
            $fail('Ten NIP się nie zgadza — sprawdź cyfry');
        }
    }
}
