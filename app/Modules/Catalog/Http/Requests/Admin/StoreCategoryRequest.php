<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Support\CategoryTree;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * „Dodaj półkę” (MA-351): a name, an English name if the shop has /en/, and the shelf it goes under — Glina, Szycie
 * or one of their shelves, so the tree stays at most three levels deep.
 */
class StoreCategoryRequest extends FormRequest
{
    public const FORM = 'nowa-polka';

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
            'name' => ['required', 'string', 'max:60'],
            'en' => ['nullable', 'array'],
            'en.name' => ['nullable', 'string', 'max:60'],
            'parent_id' => ['required', 'integer', $this->takesChildren(...)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Wpisz nazwę półki — tak zobaczą ją klienci',
            'name.max' => 'Nazwę zmieszczę do :max znaków',
            'en.name.max' => 'Nazwę zmieszczę do :max znaków',
            'parent_id.required' => 'Wybierz, gdzie ma stać nowa półka',
            'parent_id.integer' => 'Wybierz, gdzie ma stać nowa półka',
        ];
    }

    public function parent(): Category
    {
        return Category::query()->findOrFail($this->integer('parent_id'));
    }

    public function name(): string
    {
        return trim((string) $this->validated('name'));
    }

    public function englishName(): ?string
    {
        $name = trim((string) $this->validated('en.name'));

        return $name === '' ? null : $name;
    }

    private function takesChildren(string $attribute, mixed $value, Closure $fail): void
    {
        $tree = CategoryTree::load();
        $parent = $tree->get((int) $value);

        if ($parent === null || ! $tree->shopTop()->contains('id', $tree->path($parent)->first()->id)) {
            $fail('Wybierz, gdzie ma stać nowa półka');
        } elseif ($tree->depth($parent) >= Category::MAX_DEPTH) {
            $fail('W „'.$parent->name.'” nowa półka się nie zmieści — najwyżej trzy poziomy.');
        }
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = self::FORM;
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.categories.index').'#'.self::FORM;
    }
}
