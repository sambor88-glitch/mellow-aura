<?php

namespace App\Modules\Checkout\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A complaint that came by e-mail, saved on the day it arrived (MA-261): who sent it, when, and what she wrote.
 * Its errors live in their own bag, so they don't mark the answer form on the same page.
 */
class RecordComplaintRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'complaint';

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
            'order_number' => ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'received_on' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'description' => ['required', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $date = 'Wpisz, kiedy reklamacja do Ciebie doszła — od tego dnia liczy się 14 dni';

        return [
            'name.required' => 'Wpisz imię i nazwisko osoby, która złożyła reklamację',
            'name.max' => 'Imię i nazwisko zmieszczę do :max znaków',
            'email.required' => 'Wpisz e-mail — na niego wyślesz odpowiedź',
            'email.email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
            'email.max' => 'Ten adres e-mail jest za długi — sprawdź go jeszcze raz',
            'order_number.max' => 'Numer zamówienia zmieszczę do :max znaków, np. MA-2026-1047',
            'received_on.required' => $date,
            'received_on.date_format' => $date,
            'received_on.before_or_equal' => 'Ta data jest w przyszłości — wpisz dzień, w którym reklamacja doszła',
            'description.required' => 'Napisz krótko, co klientka zgłasza — np. „pęknięta miska, zdjęcie w mailu”',
            'description.max' => 'Opis zmieszczę do :max znaków — resztę zostaw w mailu od klientki',
        ];
    }

    public function receivedOn(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('Y-m-d', $this->validated('received_on'))->startOfDay();
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'order_number' => filled($number = $this->input('order_number')) ? mb_strtoupper(trim((string) $number)) : null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.complaints.index').'#zapisz';
    }
}
