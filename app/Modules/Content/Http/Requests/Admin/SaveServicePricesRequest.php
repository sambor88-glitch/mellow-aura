<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Enums\Service;
use App\Modules\Shared\Http\Requests\Concerns\EditsListRows;
use App\Modules\Shared\Rules\PriceBeforeReduction;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A service's price list. A price before a reduction shows crossed out only once the price is lower and the history
 * knows the lowest price from the 30 days before the reduction.
 */
class SaveServicePricesRequest extends FormRequest
{
    use EditsListRows;

    public const FIELDS = ['label', 'price', 'note', 'compare_at'];

    private const PRICE = '/^\d{1,5}([.,]\d{1,2})?$/';

    /** @var string */
    protected $errorBag = 'cennik';

    public function authorize(): bool
    {
        return true;
    }

    public function service(): Service
    {
        return Service::fromSlug((string) $this->route('service')) ?? abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $row = fn (array $rules) => ['exclude_if:prices.*.remove,1', ...$rules];

        return [
            'prices' => ['nullable', 'array', 'max:12'],
            'prices.*.label' => $row(['nullable', 'string', 'max:80', 'required_with:prices.*.price']),
            'prices.*.price' => $row(['nullable', 'regex:'.self::PRICE, 'required_with:prices.*.label']),
            'prices.*.compare_at' => $row(['nullable', new PriceBeforeReduction]),
            'prices.*.note' => $row(['nullable', 'string', 'max:120']),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'prices.max' => 'Zmieszczę do :max pozycji',
            'prices.*.label.required_with' => 'Wpisz nazwę pozycji do tej ceny',
            'prices.*.label.max' => 'Nazwę zmieszczę do :max znaków',
            'prices.*.price.required_with' => 'Wpisz cenę, np. 149',
            'prices.*.price.regex' => 'Wpisz cenę, np. 149 albo 149,50',
            'prices.*.note.max' => 'Dopisek zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array<string, list<array{label: ?string, note: ?string, price_gross: int, compare_at_price: ?int}>>
     */
    public function settings(): array
    {
        return [
            $this->service()->setting('prices') => array_map(fn (array $row) => [
                'label' => $row['label'],
                'note' => $row['note'],
                'price_gross' => Money::parse((string) $row['price']),
                'compare_at_price' => $row['compare_at'] === null ? null : Money::parse($row['compare_at']),
            ], $this->listRows('prices', self::FIELDS)),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.services.edit', $this->route('service')).'#cennik';
    }
}
