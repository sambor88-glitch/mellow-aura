<?php

namespace App\Modules\Firing\Http\Requests;

use App\Modules\Firing\Support\FiringQuote;
use App\Modules\Firing\Support\KilnPrices;
use App\Modules\Firing\Support\LoadDays;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The batch form: who, which service from the price list (or „Nie wiem, doradź”), how many kilograms or pieces,
 * the day to bring the work and a note. The „website” field is hidden from people; a form that fills it in comes from a bot.
 */
class ReportBatchRequest extends FormRequest
{
    /** The option for a customer who doesn't know which firing she needs. */
    public const ADVICE = 'advice';

    /** @var array<string, mixed>|null|false */
    private array|null|false $service = false;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $codes = $this->container->make(KilnPrices::class)->all()->map(fn (array $row) => KilnPrices::key($row));
        $days = $this->container->make(LoadDays::class)->upcoming()->keys();
        $unit = $this->service()['unit'] ?? null;

        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'service' => ['required', Rule::in([...$codes, self::ADVICE])],
            'quantity' => match ($unit) {
                'kg' => ['required', 'regex:/^\d{1,3}([.,]\d{1,2})?$/', 'not_regex:/^0+([.,]0*)?$/'],
                'piece' => ['required', 'integer', 'min:1', 'max:500'],
                default => ['exclude'],
            },
            'drop_off_on' => $days->isEmpty() ? ['exclude'] : ['required', Rule::in($days->all())],
            'message' => ['nullable', 'string', 'max:2000'],
            'website' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Wpisz imię — żebym wiedziała, jak się do Ciebie zwracać',
            'name.max' => 'Imię zmieszczę do :max znaków',
            'email.required' => 'Wpisz e-mail — wyślę na niego wycenę',
            'email.email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
            'email.max' => 'Ten adres e-mail jest za długi — sprawdź go jeszcze raz',
            'phone.max' => 'Numer telefonu zmieszczę do :max znaków',
            'service.required' => 'Wybierz rodzaj wypału — albo „Nie wiem, doradź”',
            'service.in' => 'Wybierz rodzaj wypału z listy',
            'quantity.required' => ($this->service()['unit'] ?? null) === 'kg'
                ? 'Wpisz, ile mniej więcej ważą prace, np. 2,5'
                : 'Wpisz, ile prac przywieziesz',
            'quantity.regex' => 'Wpisz wagę w kilogramach, np. 2,5',
            'quantity.not_regex' => 'Wpisz wagę w kilogramach, np. 2,5',
            'quantity.integer' => 'Wpisz liczbę prac, np. 4',
            'quantity.min' => 'Wpisz liczbę prac, np. 4',
            'quantity.max' => 'Przy więcej niż :max pracach napisz do mnie przez formularz kontaktowy',
            'drop_off_on.required' => 'Wybierz dzień, w którym przywieziesz prace',
            'drop_off_on.in' => 'Ten dzień już minął — wybierz któryś z listy',
            'message.max' => 'Wiadomość zmieszczę do :max znaków — resztę dopiszesz w odpowiedzi',
        ];
    }

    /**
     * The chosen row of the price list, or null for „Nie wiem, doradź” and a code that is not on the list.
     *
     * @return array{code: ?string, label: string, price_gross: int, unit: ?string}|null
     */
    public function service(): ?array
    {
        if ($this->service === false) {
            $code = (string) $this->input('service');

            $this->service = $this->container->make(KilnPrices::class)->all()
                ->first(fn (array $row) => KilnPrices::key($row) === $code);
        }

        return $this->service;
    }

    /**
     * What the customer typed, as it goes into the quote: „2.5” for kilograms, „3” for pieces.
     */
    public function quantity(): ?string
    {
        $service = $this->service();

        if ($service === null || ! in_array($service['unit'], FiringQuote::BY_AMOUNT, true)) {
            return null;
        }

        return str_replace(',', '.', trim((string) $this->validated('quantity')));
    }

    public function isFromBot(): bool
    {
        return filled($this->input('website'));
    }

    protected function getRedirectUrl(): string
    {
        return route('firing.index').'#zglos-wsad';
    }
}
