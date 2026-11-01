<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Saves a category's name, its title and description for Google and its introduction. The address stays: it was set
 * when the category was made, and renaming never breaks a link (MA-351).
 *
 * Another language follows its name: a name where there was none gives the category a row and an address in that
 * language, an emptied one removes the row and the category leaves that language's shop. Texts in a language are
 * saved only where the category has a name in it — without one the category does not exist on that page.
 */
class SaveCategoryTexts
{
    /**
     * @param  array<string, ?string>  $texts  name (when sent), seo_title, seo_description, intro
     * @param  array<string, array<string, ?string>>  $translations  per language; a „name” key only when the form sent one
     */
    public function __invoke(Category $category, array $texts, array $translations = []): void
    {
        DB::transaction(function () use ($category, $texts, $translations) {
            $category->fill(array_filter($texts, fn (?string $value, string $key) => $key !== 'name' || filled($value), ARRAY_FILTER_USE_BOTH))->save();

            foreach ($translations as $locale => $fields) {
                $row = $category->translations()->where('locale', $locale)->first();

                if (array_key_exists('name', $fields) && blank($fields['name'])) {
                    $row?->delete();

                    continue;
                }

                if ($row === null && filled($fields['name'] ?? null)) {
                    $category->translations()->create([...$fields, 'locale' => $locale, 'slug' => CreateCategory::uniqueSlug($fields['name'], $locale)]);

                    continue;
                }

                $row?->fill(array_filter($fields, fn (?string $value, string $key) => $key !== 'name' || filled($value), ARRAY_FILTER_USE_BOTH))->save();
            }
        });
    }
}
