<?php

namespace App\Modules\Checkout\Http\Requests;

use App\Modules\Cart\Cart;
use App\Modules\Cart\CartLine;
use App\Modules\Checkout\Enums\PaymentMethod;
use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\Localization\Support\Locales;
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
            // Only the ways to pay in the currency of the basket: BLIK never takes euro.
            'payment_method' => ['required', Rule::in(array_column(PaymentMethod::for(Locales::currency()), 'value'))],
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
        $t = fn (string $key) => __('checkout::validation.'.$key);

        return [
            'phone.required' => $t($this->needsDelivery() ? 'phone_required_parcel' : 'phone_required'),
            'phone.regex' => $t('phone_regex'),
            'email.required' => $t('email_required'),
            'email.email' => $t('email_email'),
            'email.max' => $t('email_max'),
            'name.required' => $t('name_required'),
            'name.max' => $t('name_max'),
            'street.required_with' => $t('street_required_with'),
            'street.max' => $t('street_max'),
            'postal_code.required_with' => $t('postal_code_required_with'),
            'postal_code.regex' => $t('postal_code_regex'),
            'city.required_with' => $t('city_required_with'),
            'city.max' => $t('city_max'),
            'invoice_nip.digits' => $t('nip_digits'),
            'note.max' => $t('note_max'),
            'shipping_method.required' => $t('shipping_method'),
            'shipping_method.in' => $t('shipping_method'),
            'payment_method.required' => $t('payment_method'),
            'payment_method.in' => $t('payment_method'),
            'blik_code.required' => $t('blik_code'),
            'blik_code.digits' => $t('blik_code'),
            'accept_terms.accepted' => $t('accept_terms'),
            ...$this->deviationKeys()->mapWithKeys(fn (string $key) => [
                'accept_deviations.'.$key.'.accepted' => $t('accept_deviation'),
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
            $fail(__('checkout::validation.nip_checksum'));
        }
    }
}
