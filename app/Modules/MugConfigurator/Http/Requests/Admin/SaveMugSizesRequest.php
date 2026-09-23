<?php

namespace App\Modules\MugConfigurator\Http\Requests\Admin;

use App\Modules\MugConfigurator\Support\MugOptions;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sizes with capacity and price, and how much text fits on the mug. A row left empty is not saved.
 */
class SaveMugSizesRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'rozmiary';

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
            'sizes' => ['required', 'array', 'min:1', 'max:8'],
            'sizes.*.label' => ['required', 'string', 'max:30', 'distinct:ignore_case'],
            'sizes.*.capacity' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'sizes.*.price' => ['required', 'regex:/^\d{1,5}([.,]\d{1,2})?$/'],
            // For the English shop; empty leaves the size out of it.
            'sizes.*.price_eur' => ['nullable', 'regex:/^\d{1,5}([.,]\d{1,2})?$/'],
            'max_chars_per_line' => ['required', 'integer', 'min:4', 'max:'.MugOptions::MAX_CHARS_PER_LINE],
            'max_lines' => ['required', 'integer', 'min:1', 'max:'.MugOptions::MAX_LINES],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'sizes.required' => 'Zostaw choć jeden rozmiar z ceną',
            'sizes.min' => 'Zostaw choć jeden rozmiar z ceną',
            'sizes.max' => 'Zmieszczę do :max rozmiarów',
            'sizes.*.label.required' => 'Nazwij rozmiar — np. Średni',
            'sizes.*.label.max' => 'Nazwę rozmiaru zmieszczę do :max znaków',
            'sizes.*.label.distinct' => 'Dwa rozmiary mają tę samą nazwę — klientka ich nie odróżni',
            'sizes.*.capacity.*' => 'Pojemność to liczba mililitrów, np. 300 — albo puste pole',
            'sizes.*.price.required' => 'Wpisz cenę, np. 79 albo 79,90',
            'sizes.*.price.regex' => 'Wpisz cenę, np. 79 albo 79,90',
            'sizes.*.price_eur.regex' => 'Wpisz cenę w euro, np. 19 albo 19,50 — albo zostaw puste pole',
            'max_chars_per_line.*' => 'Znaków w linii: od 4 do '.MugOptions::MAX_CHARS_PER_LINE,
            'max_lines.*' => 'Liczba linii: od 1 do '.MugOptions::MAX_LINES,
        ];
    }

    /**
     * @return array{mug_sizes: list<array{label: string, capacity_ml: ?int, price_gross: int, price_eur: ?int}>, mug_max_chars_per_line: int, mug_max_lines: int}
     */
    public function settings(): array
    {
        return [
            'mug_sizes' => collect((array) $this->validated('sizes'))
                ->map(fn (array $row) => [
                    'label' => trim($row['label']),
                    'capacity_ml' => filled($row['capacity'] ?? null) ? (int) $row['capacity'] : null,
                    'price_gross' => Money::parse((string) $row['price']),
                    'price_eur' => filled($row['price_eur'] ?? null) ? Money::parse((string) $row['price_eur']) : null,
                ])
                ->values()
                ->all(),
            'mug_max_chars_per_line' => (int) $this->validated('max_chars_per_line'),
            'mug_max_lines' => (int) $this->validated('max_lines'),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sizes' => collect((array) $this->input('sizes', []))
                ->filter(fn (mixed $row) => is_array($row) && ! ($row['remove'] ?? false)
                    && (filled($row['label'] ?? null) || filled($row['capacity'] ?? null) || filled($row['price'] ?? null)))
                ->map(fn (array $row) => [
                    'label' => is_string($row['label'] ?? null) ? trim($row['label']) : ($row['label'] ?? null),
                    'capacity' => is_string($row['capacity'] ?? null) ? trim($row['capacity'], " \u{00A0}ml") : ($row['capacity'] ?? null),
                    'price' => is_string($row['price'] ?? null) ? trim($row['price'], " \u{00A0}zł") : ($row['price'] ?? null),
                    'price_eur' => is_string($row['price_eur'] ?? null) ? trim($row['price_eur'], " \u{00A0}€") : ($row['price_eur'] ?? null),
                ])
                ->values()
                ->all(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.mug.edit').'#rozmiary';
    }
}
