<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Enums\Dimension;
use App\Modules\Catalog\Enums\FoodContact;
use App\Modules\Catalog\Enums\GoogleCategory;
use App\Modules\Catalog\Enums\Occasion;
use App\Modules\Catalog\Enums\Recipient;
use App\Modules\Shared\Rules\PriceBeforeReduction;
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
            'food_contact' => ['nullable', Rule::enum(FoodContact::class)],
            'deviation' => ['nullable', 'string', 'max:160'],
            'size_tolerance' => ['nullable', 'string', 'max:60'],
            'safety_warnings' => ['nullable', 'string', 'max:400'],
            'google_category' => ['nullable', Rule::enum(GoogleCategory::class)],
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
            'variants.*.compare_at' => ['nullable', new PriceBeforeReduction],
            // The euro price, typed in by hand. A size without one stays out of the English shop.
            'variants.*.price_eur' => ['nullable', 'regex:/^\d{1,5}([.,]\d{1,2})?$/'],
            'variants.*.compare_at_eur' => ['nullable', new PriceBeforeReduction('price_eur')],
            'variants.*.stock' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'variants.*.sent_by_post' => ['nullable', 'boolean'],
            // Only a photo of this product; SaveProduct checks that it belongs here.
            'variants.*.media_id' => ['nullable', 'integer'],
            'photo_alts' => ['nullable', 'array'],
            'photo_alts.*' => ['nullable', 'string', 'max:160'],
            // The English version: optional, but once the product has an English name every size needs one too,
            // or the English page would show a size without a name.
            'en' => ['nullable', 'array'],
            'en.name' => ['nullable', 'string', 'max:120'],
            'en.description' => ['nullable', 'string', 'max:2000'],
            'en.care_note' => ['nullable', 'string', 'max:200'],
            // A feature to confirm and the safety warnings must reach an English buyer too: the checkout asks to accept
            // the first, and product safety law wants the second in the buyer's language.
            'en.deviation' => [filled($this->input('en.name')) && filled($this->input('deviation')) ? 'required' : 'nullable', 'string', 'max:160'],
            'en.size_tolerance' => ['nullable', 'string', 'max:60'],
            'en.safety_warnings' => [filled($this->input('en.name')) && filled($this->input('safety_warnings')) ? 'required' : 'nullable', 'string', 'max:400'],
            'variants.*.label_en' => [filled($this->input('en.name')) && count((array) $this->input('variants')) > 1 ? 'required' : 'nullable', 'string', 'max:60'],
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
            'food_contact.enum' => 'Wybierz z listy, czy to naczynie do jedzenia',
            'deviation.max' => 'Tę cechę zmieszczę do :max znaków — napisz ją krócej',
            'size_tolerance.max' => 'Różnicę wymiarów zmieszczę do :max znaków, np. 0,5 cm',
            'safety_warnings.max' => 'Ostrzeżenia zmieszczę do :max znaków',
            'google_category.enum' => 'Wybierz rodzaj z listy albo zostaw „Niech Google dobierze sam”',
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
            'variants.*.price_eur.regex' => 'Wpisz cenę w euro, np. 39 albo 39,90 — albo zostaw puste pole',
            'variants.*.stock.integer' => $stock,
            'variants.*.stock.min' => $stock,
            'variants.*.stock.max' => $stock,
            'variants.*.media_id.integer' => 'Wybierz zdjęcie z listy',
            'photo_alts.*.max' => 'Opis zdjęcia zmieszczę do :max znaków',
            'en.name.max' => 'Angielską nazwę zmieszczę do :max znaków',
            'en.description.max' => 'Angielski opis zmieszczę do :max znaków',
            'en.care_note.max' => 'Zdanie o pielęgnacji po angielsku zmieszczę do :max znaków',
            'en.deviation.required' => 'Produkt ma angielską nazwę i cechę do potwierdzenia — wpisz ją też po angielsku, bo klientka z zagranicy musi ją zaakceptować przy zamówieniu',
            'en.deviation.max' => 'Tę cechę po angielsku zmieszczę do :max znaków — napisz ją krócej',
            'en.size_tolerance.max' => 'Różnicę wymiarów po angielsku zmieszczę do :max znaków, np. 0.5 cm',
            'en.safety_warnings.required' => 'Produkt ma angielską nazwę i ostrzeżenia — wpisz je też po angielsku, prawo wymaga ich w języku kupującej',
            'en.safety_warnings.max' => 'Ostrzeżenia po angielsku zmieszczę do :max znaków',
            'variants.*.label_en.required' => 'Produkt ma angielską nazwę, więc nazwij każdy rozmiar także po angielsku — np. Small 12 cm',
            'variants.*.label_en.max' => 'Angielską nazwę rozmiaru zmieszczę do :max znaków',
            ...PhotoRules::messages(),
        ];
    }

    /**
     * The validated form in the shape SaveProduct takes: prices in grosze, empty dimensions left out,
     * photo descriptions keyed by photo id.
     *
     * @return array{name: string, category_id: int, description: ?string, care_note: ?string, food_contact: ?string, deviation: ?string, size_tolerance: ?string, safety_warnings: ?string, google_category: ?int, show_in_google: bool, is_published: bool, is_one_off: bool, is_exact_piece: bool, dimensions: array<string, string>, occasions: list<string>, recipients: list<string>, variants: list<array{id: ?int, label: string, labels: array<string, string>, price_gross: int, compare_at_price: ?int, prices: array<string, array{amount: ?int, compare_at: ?int}>, stock: ?int, sent_by_post: bool, media_id: ?int}>, photo_alts: array<int, string>}
     */
    public function product(): array
    {
        $data = $this->validated();

        return [
            'name' => $data['name'],
            'category_id' => (int) $data['category_id'],
            'description' => $data['description'] ?? null,
            'care_note' => $data['care_note'] ?? null,
            'food_contact' => $data['food_contact'] ?? null,
            'deviation' => $data['deviation'] ?? null,
            'size_tolerance' => $data['size_tolerance'] ?? null,
            'safety_warnings' => $data['safety_warnings'] ?? null,
            'google_category' => isset($data['google_category']) ? (int) $data['google_category'] : null,
            'show_in_google' => $this->boolean('show_in_google'),
            'is_published' => $this->boolean('is_published'),
            'is_one_off' => $this->boolean('is_one_off'),
            'is_exact_piece' => $this->boolean('is_exact_piece'),
            'dimensions' => array_filter(
                Arr::only((array) ($data['dimensions'] ?? []), array_column(Dimension::cases(), 'value')),
                fn (mixed $value) => filled($value),
            ),
            'occasions' => array_values($data['occasions'] ?? []),
            'recipients' => array_values($data['recipients'] ?? []),
            'variants' => array_map(fn (array $row) => [
                'id' => isset($row['id']) ? (int) $row['id'] : null,
                'label' => trim((string) ($row['label'] ?? '')),
                'labels' => ['en' => trim((string) ($row['label_en'] ?? ''))],
                'price_gross' => Money::parse((string) $row['price']),
                'compare_at_price' => filled($row['compare_at'] ?? null) ? Money::parse((string) $row['compare_at']) : null,
                'prices' => ['EUR' => [
                    'amount' => filled($row['price_eur'] ?? null) ? Money::parse((string) $row['price_eur']) : null,
                    'compare_at' => filled($row['compare_at_eur'] ?? null) ? Money::parse((string) $row['compare_at_eur']) : null,
                ]],
                'stock' => isset($row['stock']) ? (int) $row['stock'] : null,
                'sent_by_post' => (bool) ($row['sent_by_post'] ?? false),
                'media_id' => filled($row['media_id'] ?? null) ? (int) $row['media_id'] : null,
            ], $data['variants']),
            'photo_alts' => array_map(fn (mixed $alt) => trim((string) $alt), (array) ($data['photo_alts'] ?? [])),
            'translations' => ['en' => array_map(
                fn (mixed $value) => filled($value) ? trim((string) $value) : null,
                Arr::only((array) ($data['en'] ?? []), ['name', 'description', 'care_note', 'deviation', 'size_tolerance', 'safety_warnings']),
            )],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->formKey();

        $variants = collect((array) $this->input('variants', []))
            ->filter(fn (mixed $row) => is_array($row) && ! ($row['remove'] ?? false))
            // A row left empty is a spare slot for another size.
            ->reject(fn (array $row) => blank($row['id'] ?? null) && blank($row['label'] ?? null) && blank($row['price'] ?? null) && blank($row['compare_at'] ?? null) && blank($row['price_eur'] ?? null) && blank($row['stock'] ?? null))
            ->values()
            ->all();

        $this->merge(['variants' => $variants, 'form' => $this->formKey()]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.products.index').'#'.$this->formKey();
    }
}
