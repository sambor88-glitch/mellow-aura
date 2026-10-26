<?php

namespace App\Modules\CustomOrders\Http\Requests\Admin;

use App\Modules\CustomOrders\Enums\CustomOrderStatus;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One idea in the panel: its status, Kasia's price in złoty and her note.
 */
class UpdateCustomOrderRequestRequest extends FormRequest
{
    private const PRICE = '/^\d{1,6}([.,]\d{1,2})?$/';

    /** @var string */
    protected $errorBag = 'pomysl';

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
            'status' => ['required', Rule::enum(CustomOrderStatus::class)],
            'quote' => ['nullable', 'regex:'.self::PRICE],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.*' => 'Wybierz status z listy',
            'quote.*' => 'Wpisz kwotę wyceny, np. 450 albo 450,50',
            'notes.*' => 'Notatka może mieć do 5000 znaków',
        ];
    }

    /**
     * @return array{status: string, quote: ?int, notes: ?string}
     */
    public function changes(): array
    {
        $validated = $this->validated();

        return [
            'status' => $validated['status'],
            'quote' => filled($validated['quote'] ?? null) ? Money::parse($validated['quote']) : null,
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.custom-orders.index').'#pomysl-'.$this->route('customOrderRequest')->id;
    }
}
