<?php

namespace App\Modules\Customers\Http\Requests\Admin;

use App\Modules\Customers\Support\Customer;
use App\Modules\Shared\Support\PersonalData;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Forgetting a person cannot be undone, so Kasia types her address once more — the same check GitHub asks
 * before deleting a repository. The address must be the one on the card.
 */
class AnonymizeCustomerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Customer $customer */
        $customer = $this->route('customer');

        return [
            'confirm_email' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail) use ($customer) {
                if (PersonalData::normalize((string) $value) !== $customer->email) {
                    $fail('Wpisz dokładnie adres z tej karty — to zabezpieczenie przed usunięciem nie tej osoby.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['confirm_email.required' => 'Wpisz adres e-mail tej osoby, żeby potwierdzić usunięcie.'];
    }
}
