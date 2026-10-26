<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A category's title and description for Google and its introduction above the products. Every category has its
 * own form, so mistakes come back to the category they belong to. Google cuts a title after 60 characters and
 * a description after 155; the counter warns, the columns take up to 120 and 255.
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

        return [
            'texts' => $clean($data),
            'translations' => ['en' => $clean((array) ($data['en'] ?? []))],
        ];
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
