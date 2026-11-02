<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CategoryRedirect;
use App\Modules\Catalog\Models\CategoryTranslation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * „Dodaj półkę” in the panel (MA-351): a new shelf at the end of the one it goes under, with that branch's group.
 * Its address comes from the name, once — renaming or moving it later never breaks a link. An address another
 * shelf had before (category_redirects) is taken too, so an old link never lands on the wrong shelf.
 * With an English name it also gets an English address and shows on /en/ once something on it is priced in euro.
 */
class CreateCategory
{
    public function __invoke(Category $parent, string $name, ?string $englishName = null): Category
    {
        return DB::transaction(function () use ($parent, $name, $englishName) {
            $category = Category::create([
                'parent_id' => $parent->id,
                'name' => $name,
                'slug' => self::uniqueSlug($name, 'pl'),
                'group' => $parent->group,
                'sort_order' => (int) Category::query()->where('parent_id', $parent->id)->max('sort_order') + 1,
            ]);

            if (filled($englishName)) {
                $category->translations()->create(['locale' => 'en', 'name' => $englishName, 'slug' => self::uniqueSlug($englishName, 'en')]);
            }

            return $category;
        });
    }

    /** „Osłonki na donice” → oslonki-na-donice, or oslonki-na-donice-2 when the address is taken. */
    public static function uniqueSlug(string $name, string $locale): string
    {
        $base = Str::slug($name) ?: ($locale === 'pl' ? 'kategoria' : 'category');
        $taken = fn (string $slug) => ($locale === 'pl'
                ? Category::query()->where('slug', $slug)->exists()
                : CategoryTranslation::query()->where('locale', $locale)->where('slug', $slug)->exists())
            || CategoryRedirect::query()->where('locale', $locale)->where('slug', $slug)->exists();

        $slug = $base;

        for ($suffix = 2; $taken($slug); $suffix++) {
            $slug = $base.'-'.$suffix;
        }

        return $slug;
    }
}
