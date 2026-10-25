<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Category;
use Illuminate\Support\Facades\DB;

/**
 * Saves a category's title and description for Google and its introduction. A language other than Polish is saved
 * only where the category already has a name in it — without one the category does not exist on that page.
 */
class SaveCategoryTexts
{
    /**
     * @param  array<string, ?string>  $texts
     * @param  array<string, array<string, ?string>>  $translations
     */
    public function __invoke(Category $category, array $texts, array $translations = []): void
    {
        DB::transaction(function () use ($category, $texts, $translations) {
            $category->fill($texts)->save();

            foreach ($translations as $locale => $fields) {
                $category->translations()->where('locale', $locale)->first()?->fill($fields)->save();
            }
        });
    }
}
