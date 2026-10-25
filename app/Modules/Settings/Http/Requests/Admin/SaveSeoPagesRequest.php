<?php

namespace App\Modules\Settings\Http\Requests\Admin;

use App\Modules\Shared\Support\PageSeo;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Titles and descriptions for Google of the fixed pages, per language. Google cuts a title after 60 characters
 * and a description after 155; the counter warns, the form saves up to 120 and 255.
 */
class SaveSeoPagesRequest extends FormRequest
{
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
            'pages' => ['nullable', 'array'],
            'pages.*.*.title' => ['nullable', 'string', 'max:120'],
            'pages.*.*.description' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'pages.*.*.title.max' => 'Tytuł zmieszczę do :max znaków — Google i tak pokaże tylko pierwsze 60',
            'pages.*.*.description.max' => 'Opis zmieszczę do :max znaków — Google i tak pokaże tylko pierwsze 155',
        ];
    }

    /**
     * Only known pages and languages, trimmed; a page with nothing filled in is left out.
     *
     * @return array<string, array<string, array{title: ?string, description: ?string}>>
     */
    public function pages(): array
    {
        $pages = [];

        foreach ((array) $this->validated('pages', []) as $key => $locales) {
            $page = PageSeo::pageFromFieldKey((string) $key);

            if (! array_key_exists($page, PageSeo::PAGES) || ! is_array($locales)) {
                continue;
            }

            foreach ($locales as $locale => $fields) {
                if (! in_array($locale, ['pl', 'en'], true) || ! is_array($fields)) {
                    continue;
                }

                $clean = array_map(fn (mixed $value) => filled($value) ? trim((string) $value) : null, [
                    'title' => $fields['title'] ?? null,
                    'description' => $fields['description'] ?? null,
                ]);

                if (array_filter($clean) !== []) {
                    $pages[$page][$locale] = $clean;
                }
            }
        }

        return $pages;
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.seo-pages.edit');
    }
}
