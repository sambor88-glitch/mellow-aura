<?php

namespace App\Modules\Gifts\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * How many days the person a gift choice card is for has to choose, and what the delivery to her costs,
 * paid by the buyer with the card (MA-235). An empty delivery field goes back to the cheapest way of delivery.
 */
class SaveGiftChoiceSettingsRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'ustawienia';

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
            'gift_choice_days' => ['required', 'integer', 'min:7', 'max:365'],
            'gift_choice_delivery' => ['nullable', 'regex:/^\d{1,4}([.,]\d{1,2})?$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'gift_choice_days.*' => 'Wpisz liczbę dni od 7 do 365',
            'gift_choice_delivery.*' => 'Wpisz kwotę w złotych, np. 16 albo 15,99',
        ];
    }

    /**
     * @return array<string, ?int>
     */
    public function settings(): array
    {
        $delivery = $this->validated('gift_choice_delivery');

        return [
            'gift_choice_days' => (int) $this->validated('gift_choice_days'),
            'gift_choice_delivery_gross' => filled($delivery) ? (int) round((float) str_replace(',', '.', $delivery) * 100) : null,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.gift-choices.index').'#ustawienia';
    }
}
