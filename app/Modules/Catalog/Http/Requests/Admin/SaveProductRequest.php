<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Enums\Dimension;
use App\Modules\Catalog\Enums\Occasion;
use App\Modules\Catalog\Enums\Recipient;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;

/**
 * The product form in the panel. Every product on the list has its own form, so mistakes come back
 * in that form's error bag and the page scrolls to the product they belong to.
 */
class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function formKey(): string
    {
        $product = $this->route('product');

        return $product ? 'produkt-'.$product->id : 'nowy-produkt';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')],
            'description' => ['nullable', 'string', 'max:2000'],
            'care_note' => ['nullable', 'string', 'max:200'],
            'dimensions' => ['nullable', 'array'],
            'dimensions.*' => ['nullable', 'string', 'max:20', 'regex:/^[\d\s.,x×-]+$/u'],
            'occasions' => ['nullable', 'array'],
            'occasions.*' => [Rule::enum(Occasion::class)],
            'recipients' => ['nullable', 'array'],
            'recipients.*' => [Rule::enum(Recipient::class)],
            'variants' => ['required', 'array', 'max:30'],
            'variants.*.id' => ['nullable', 'integer'],
            // One price needs no size name; several sizes do.
            'variants.*.label' => [count((array) $this->input('variants')) > 1 ? 'required' : 'nullable', 'string', 'max:60'],
            'variants.*.price' => ['required', 'regex:/^\d{1,5}([.,]\d{1,2})?$/'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'photo_alts' => ['nullable', 'array'],
            'photo_alts.*' => ['nullable', 'string', 'max:160'],
            ...PhotoRules::rules(required: false),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $category = 'Wybierz rodzaj — kategorię w sklepie';
        $price = 'Wpisz cenę, np. 79 albo 79,90';
        $stock = 'Stan to liczba sztuk — puste pole znaczy, że nie liczysz sztuk';
        $dimension = 'W wymiarach wpisz tylko liczby, np. 24 albo 12,5';

        return [
            'name.required' => 'Wpisz nazwę — np. Miska z odciskiem paproci',
            'name.max' => 'Nazwę zmieszczę do :max znaków',
            'category_id.required' => $category,
            'category_id.integer' => $category,
            'category_id.exists' => $category,
            'description.max' => 'Opis zmieszczę do :max znaków',
            'care_note.max' => 'Zdanie o pielęgnacji zmieszczę do :max znaków',
            'dimensions.*.max' => $dimension,
            'dimensions.*.regex' => $dimension,
            'occasions.*.enum' => 'Wybierz okazję z listy',
            'recipients.*.enum' => 'Wybierz z listy, dla kogo',
            'variants.required' => 'Dodaj choć jeden rozmiar z ceną',
            'variants.max' => 'Zmieszczę do :max rozmiarów',
            'variants.*.label.required' => 'Nazwij każdy rozmiar — np. Mały 12 cm',
            'variants.*.label.max' => 'Nazwę rozmiaru zmieszczę do :max znaków',
            'variants.*.price.required' => $price,
            'variants.*.price.regex' => $price,
            'variants.*.stock.integer' => $stock,
            'variants.*.stock.min' => $stock,
            'variants.*.stock.max' => $stock,
            'photo_alts.*.max' => 'Opis zdjęcia zmieszczę do :max znaków',
            ...PhotoRules::messages(),
        ];
    }

    /**
     * The validated form in the shape SaveProduct takes: prices in grosze, empty dimensions left out,
     * photo descriptions keyed by photo id.
     *
     * @return array{name: string, category_id: int, description: ?string, care_note: ?string, is_published: bool, is_one_off: bool, dimensions: array<string, string>, occasions: list<string>, recipients: list<string>, variants: list<array{id: ?int, label: string, price_gross: int, stock: ?int}>, photo_alts: array<int, string>}
     */
    public function product(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'category_id' => (int) $data['category_id'],
            'description' => $data['description'] ?? null,
            'care_note' => $data['care_note'] ?? null,
            'is_published' => $this->boolean('is_published'),
            'is_one_off' => $this->boolean('is_one_off'),
            'dimensions' => array_filter(
                Arr::only((array) ($data['dimensions'] ?? []), array_column(Dimension::cases(), 'value')),
                fn (mixed $value) => filled($value),
            ),
            'occasions' => array_values($data['occasions'] ?? []),
            'recipients' => array_values($data['recipients'] ?? []),
            'variants' => array_map(fn (array $row) => [
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'label' => trim((string) ($row['label'] ?? '')),
                'price_gross' => Money::parse((string) $row['price']),
                'stock' => isset($row['stock']) ? (int) $row['stock'] : null,
            ], $data['variants']),
            'photo_alts' => array_map(fn (mixed $alt) => trim((string) $alt), (array) ($data['photo_alts'] ?? [])),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->formKey();

        $variants = collect((array) $this->input('variants', []))
            ->filter(fn (mixed $row) => is_array($row) && ! ($row['remove'] ?? false))
            // A row left empty is a spare slot for another size.
            ->reject(fn (array $row) => blank($row['id'] ?? null) && blank($row['label'] ?? null) && blank($row['price'] ?? null) && blank($row['stock'] ?? null))
            ->values()
            ->all();

        $this->merge(['variants' => $variants, 'form' => $this->formKey()]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.products.index').'#'.$this->formKey();
    }
}
