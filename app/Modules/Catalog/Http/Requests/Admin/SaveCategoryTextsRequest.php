<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\CategoryTree;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A category's card in the panel: its name (Polish and English), where it stands in the tree (MA-351), its title and
 * description for Google and its introduction above the products. Every category has its own form, so mistakes come
 * back to the category they belong to. Google cuts a title after 60 characters and a description after 155;
 * the counter warns, the columns take up to 120 and 255.
 */
class SaveCategoryTextsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function formKey(): string
    {
        return 'kategoria-'.$this->route('category')->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Sent by the card; a form without them leaves the name and the place in the tree as they are.
            'name' => ['sometimes', 'required', 'string', 'max:60'],
            'parent_id' => ['sometimes', 'integer', $this->fitsUnder(...)],
            'en.name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'seo_title' => ['nullable', 'string', 'max:120'],
            'seo_description' => ['nullable', 'string', 'max:255'],
            'intro' => ['nullable', 'string', 'max:600'],
            'en' => ['nullable', 'array'],
            'en.seo_title' => ['nullable', 'string', 'max:120'],
            'en.seo_description' => ['nullable', 'string', 'max:255'],
            'en.intro' => ['nullable', 'string', 'max:600'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $title = 'Tytuł zmieszczę do :max znaków — Google i tak pokaże tylko pierwsze 60';
        $description = 'Opis zmieszczę do :max znaków — Google i tak pokaże tylko pierwsze 155';
        $intro = 'Wstęp zmieszczę do :max znaków — dwa, trzy zdania wystarczą';

        return [
            'name.required' => 'Wpisz nazwę półki — tak zobaczą ją klienci',
            'name.max' => 'Nazwę zmieszczę do :max znaków',
            'en.name.max' => 'Nazwę zmieszczę do :max znaków',
            'parent_id.integer' => 'Wybierz z listy, gdzie stoi półka',
            'seo_title.max' => $title,
            'seo_description.max' => $description,
            'intro.max' => $intro,
            'en.seo_title.max' => $title,
            'en.seo_description.max' => $description,
            'en.intro.max' => $intro,
        ];
    }

    /**
     * Trimmed texts, an empty field as null: the page then composes the title and description itself.
     *
     * @return array{texts: array<string, ?string>, translations: array<string, array<string, ?string>>}
     */
    public function texts(): array
    {
        $clean = fn (array $fields) => array_map(
            fn (mixed $value) => filled($value) ? trim((string) $value) : null,
            array_merge(['seo_title' => null, 'seo_description' => null, 'intro' => null], array_intersect_key($fields, array_flip(['seo_title', 'seo_description', 'intro']))),
        );
        $data = $this->validated();
        $texts = $clean($data);
        $english = $clean((array) ($data['en'] ?? []));

        if (array_key_exists('name', $data)) {
            $texts['name'] = trim((string) $data['name']);
        }

        // Only a name the form sent: an emptied one takes the category off /en/, a missing one changes nothing.
        if ($this->has('en.name')) {
            $english['name'] = filled($data['en']['name'] ?? null) ? trim((string) $data['en']['name']) : null;
        }

        return [
            'texts' => $texts,
            'translations' => ['en' => $english],
        ];
    }

    /** The shelf to move under, or null to leave it where it is. */
    public function parent(): ?Category
    {
        return $this->has('parent_id') ? Category::query()->find($this->integer('parent_id')) : null;
    }

    /**
     * Under Glina or Szycie, not under itself, and no deeper than three levels with everything under it.
     */
    private function fitsUnder(string $attribute, mixed $value, Closure $fail): void
    {
        $tree = CategoryTree::load();
        $category = $tree->get($this->route('category')->id);
        $parent = $tree->get((int) $value);

        if ($parent === null) {
            $fail('Wybierz z listy, gdzie stoi półka');

            return;
        }

        if ($category === null || $category->parent_id === $parent->id) {
            return;
        }

        $problem = match (true) {
            $category->isTop() => 'Kategoria główna zostaje na samej górze.',
            ! $tree->shopTop()->contains('id', $tree->path($parent)->first()->id) => 'Półka może stać tylko w Glinie albo w Szyciu.',
            in_array($parent->id, $tree->subtreeIds($category), true) => 'Półka nie może stać w samej sobie ani w swojej podkategorii.',
            $tree->depth($parent) + $tree->height($category) > Category::MAX_DEPTH => 'Tu się nie zmieści — najwyżej trzy poziomy, np. Glina › Biżuteria › Kolczyki.',
            default => null,
        };

        if ($problem !== null) {
            $fail($problem);
        }
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->formKey();
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.categories.index').'#'.$this->formKey();
    }
}
