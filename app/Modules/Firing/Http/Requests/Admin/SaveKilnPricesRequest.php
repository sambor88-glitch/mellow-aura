<?php

namespace App\Modules\Firing\Http\Requests\Admin;

use App\Modules\Shared\Http\Requests\Concerns\EditsListRows;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * The firing price list with the sentences above and under it. A row keeps its code and unit for the batch
 * form after the holidays; a new row gets a code from its name.
 */
class SaveKilnPricesRequest extends FormRequest
{
    use EditsListRows;

    public const FIELDS = ['label', 'price', 'unit_label', 'note', 'code', 'unit'];

    private const PRICE = '/^\d{1,5}([.,]\d{1,2})?$/';

    /** @var string */
    protected $errorBag = 'wypaly';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $row = fn (array $rules) => ['exclude_if:prices.*.remove,1', ...$rules];

        return [
            'text_kiln_lead' => ['nullable', 'string', 'max:300'],
            'text_kiln_note' => ['nullable', 'string', 'max:400'],
            'prices' => ['nullable', 'array', 'max:15'],
            'prices.*.label' => $row(['nullable', 'string', 'max:80', 'required_with:prices.*.price']),
            'prices.*.price' => $row(['nullable', 'regex:'.self::PRICE, 'required_with:prices.*.label']),
            'prices.*.unit_label' => $row(['nullable', 'string', 'max:20']),
            'prices.*.note' => $row(['nullable', 'string', 'max:120']),
            'prices.*.code' => ['nullable', 'string', 'max:60'],
            'prices.*.unit' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text_kiln_lead.max' => 'Zdanie zmieszczę do :max znaków',
            'text_kiln_note.max' => 'Notkę zmieszczę do :max znaków',
            'prices.max' => 'Zmieszczę do :max pozycji',
            'prices.*.label.required_with' => 'Wpisz nazwę usługi do tej ceny',
            'prices.*.label.max' => 'Nazwę zmieszczę do :max znaków',
            'prices.*.price.required_with' => 'Wpisz cenę, np. 40',
            'prices.*.price.regex' => 'Wpisz cenę, np. 40 albo 40,50',
            'prices.*.unit_label.max' => 'To pole zmieszczę do :max znaków',
            'prices.*.note.max' => 'Dopisek zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array{text_kiln_lead: ?string, text_kiln_note: ?string, kiln_prices: list<array<string, mixed>>}
     */
    public function settings(): array
    {
        return [
            'text_kiln_lead' => filled($lead = $this->validated('text_kiln_lead')) ? trim($lead) : null,
            'text_kiln_note' => filled($note = $this->validated('text_kiln_note')) ? trim($note) : null,
            'kiln_prices' => array_map(fn (array $row) => [
                'code' => $row['code'] ?? Str::slug((string) $row['label'], '_'),
                'label' => $row['label'],
                'note' => $row['note'],
                'price_gross' => Money::parse((string) $row['price']),
                'unit' => $row['unit'],
                'unit_label' => $row['unit_label'],
            ], $this->listRows('prices', self::FIELDS)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.firing.edit').'#cennik';
    }
}
