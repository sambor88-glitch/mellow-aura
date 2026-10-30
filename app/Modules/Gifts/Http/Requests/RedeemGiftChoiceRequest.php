<?php

namespace App\Modules\Gifts\Http\Requests;

use App\Modules\Checkout\Support\ShippingMethods;
use App\Modules\MugConfigurator\Support\MugOptions;
use App\Modules\MugConfigurator\Support\MugText;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Validator;

/**
 * The choice on a gift choice card (MA-235): the text, checked like the configurator's (MugText), the inside glaze,
 * and where to send the mug — the same fields and sentences as the checkout's delivery. The text is limited and
 * tidied here; every page that shows it escapes it.
 */
class RedeemGiftChoiceRequest extends FormRequest
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
        [$textRules] = MugText::rules();

        return [
            'text' => $textRules,
            'glaze' => ['required', Rule::in($this->container->make(MugOptions::class)->glazes()->pluck('code')->all())],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['required', 'string', 'regex:/^(\+?48)?[\s-]*(\d[\s-]*){9}$/'],
            'name' => ['required', 'string', 'max:120'],
            'shipping_method' => ['required', Rule::in($this->container->make(ShippingMethods::class)->all()->keys())],
            'locker_code' => ['exclude_unless:shipping_method,parcel_locker', 'required', 'regex:/^[A-Z0-9-]{4,16}$/'],
            'street' => ['exclude_unless:shipping_method,courier', 'required', 'string', 'max:160'],
            'postal_code' => ['exclude_unless:shipping_method,courier', 'required', 'regex:/^\d{2}-\d{3}$/'],
            'city' => ['exclude_unless:shipping_method,courier', 'required', 'string', 'max:80'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        [, $textMessages] = MugText::rules();
        $t = fn (string $key) => __('checkout::validation.'.$key);

        return [
            ...$textMessages,
            'glaze.*' => __('mug-configurator::mug.validation.glaze'),
            'email.required' => $t('email_required'),
            'email.email' => $t('email_email'),
            'email.max' => $t('email_max'),
            'phone.required' => $t('phone_required_parcel'),
            'phone.regex' => $t('phone_regex'),
            'name.required' => $t('name_required'),
            'name.max' => $t('name_max'),
            'shipping_method.*' => $t('shipping_method'),
            'locker_code.required' => $t('locker_code_required'),
            'locker_code.regex' => $t('locker_code'),
            'street.required' => $t('street_required'),
            'street.max' => $t('street_max'),
            'postal_code.required' => $t('postal_code_required'),
            'postal_code.regex' => $t('postal_code_regex'),
            'city.required' => $t('city_required'),
            'city.max' => $t('city_max'),
        ];
    }

    /**
     * The lines and characters from the panel, once the text itself is fine.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->has('text')) {
                return;
            }

            try {
                $this->container->make(MugText::class)->fits((string) $this->input('text'));
            } catch (ValidationException $tooLong) {
                $validator->errors()->add('text', (string) collect($tooLong->errors()['text'] ?? [])->first());
            }
        }];
    }

    /**
     * The glaze as the order keeps it.
     *
     * @return array{code: string, name: string, hex: string}
     */
    public function glaze(): array
    {
        return $this->container->make(MugOptions::class)->glazes()->firstWhere('code', $this->validated('glaze'));
    }

    /**
     * Her name, contact and delivery, in the shape PlaceOrder takes.
     *
     * @return array<string, mixed>
     */
    public function delivery(): array
    {
        return collect($this->validated())->except(['text', 'glaze'])->all();
    }

    protected function prepareForValidation(): void
    {
        $postalCode = (string) $this->input('postal_code');

        $this->merge([
            'text' => MugText::tidy($this->input('text')),
            'locker_code' => strtoupper((string) preg_replace('/\s+/', '', (string) $this->input('locker_code'))) ?: null,
            'postal_code' => preg_match('/^\s*(\d{2})[\s-]*(\d{3})\s*$/', $postalCode, $digits) ? $digits[1].'-'.$digits[2] : $this->input('postal_code'),
        ]);
    }
}
