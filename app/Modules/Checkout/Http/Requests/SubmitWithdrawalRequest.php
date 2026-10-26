<?php

namespace App\Modules\Checkout\Http\Requests;

use App\Modules\Checkout\Enums\WithdrawalScope;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The withdrawal statement: who, which order, the whole of it or a part, and where the confirmation goes.
 * No reason is asked for — withdrawing needs none.
 */
class SubmitWithdrawalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * „ma 2026 1047”, „2026-1047” and „MA-2026-1047” all mean the same order.
     */
    protected function prepareForValidation(): void
    {
        $number = mb_strtoupper(trim((string) $this->input('order_number')));

        if (preg_match('/^(?:MA)?[\s-]*(\d{4})[\s\/-]*(\d{4,})$/', $number, $parts)) {
            $number = 'MA-'.$parts[1].'-'.$parts[2];
        }

        $this->merge(['order_number' => $number]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'order_number' => ['required', 'string', 'max:40'],
            'scope' => ['required', Rule::enum(WithdrawalScope::class)],
            'items' => ['nullable', 'string', 'max:2000', 'required_if:scope,'.WithdrawalScope::Part->value],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Wpisz imię i nazwisko — tak jak w zamówieniu',
            'name.max' => 'Imię i nazwisko zmieszczę do :max znaków',
            'email.required' => 'Wpisz e-mail — wyślę na niego potwierdzenie z datą i godziną',
            'email.email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
            'email.max' => 'Ten adres e-mail jest za długi — sprawdź go jeszcze raz',
            'order_number.required' => 'Wpisz numer zamówienia z maila, np. MA-2026-1047',
            'order_number.max' => 'Numer zamówienia wygląda tak: MA-2026-1047',
            'scope.required' => 'Zaznacz, czy odstępujesz od całego zamówienia, czy od części',
            'scope.enum' => 'Zaznacz, czy odstępujesz od całego zamówienia, czy od części',
            'items.required_if' => 'Napisz, od których rzeczy odstępujesz',
            'items.max' => 'Opis zmieszczę do :max znaków',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('withdrawal.create');
    }
}
