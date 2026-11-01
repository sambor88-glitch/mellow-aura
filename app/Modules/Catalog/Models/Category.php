<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\CategoryFactory;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Localization\Support\Locales;
use App\Modules\Shared\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * A shelf in the shop, in a tree of up to three levels (MA-351): Glina and Szycie at the top, then e.g. Biżuteria,
 * then Kolczyki. The group (ceramics, crafts, workshops) comes from the top of the tree and decides the care rule.
 * Vouchers stand at the top outside the tree, with their own page.
 *
 * The address is set once, when the category is made: renaming or moving it in the tree never breaks a link.
 */
#[Fillable(['parent_id', 'slug', 'name', 'group', 'sort_order', 'seo_title', 'seo_description', 'intro'])]
class Category extends Model
{
    /** @use HasFactory<CategoryFactory> */
    use HasFactory, HasTranslations {
        HasTranslations::resolveRouteBinding as resolveTranslatedRouteBinding;
    }

    /** Glina → Biżuteria → Kolczyki. Deeper does not read on a phone (Maciej, 1.11.2026). */
    public const MAX_DEPTH = 3;

    /** The shelf that opens with the mug configurator, before its products. */
    public const PERSONALISED_MUGS = 'kubki-z-wlasnym-napisem';

    /** Read in the language of the page; the Polish text lives in the columns themselves. */
    protected array $translatable = ['slug', 'name', 'seo_title', 'seo_description', 'intro'];

    /**
     * @return BelongsTo<Category, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<Category, $this>
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Products whose main shelf this is.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Products from other shelves shown here too („Pokaż też w” on the product card).
     *
     * @return BelongsToMany<Product, $this>
     */
    public function alsoShownProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'category_product');
    }

    /**
     * @return HasMany<CategoryRedirect, $this>
     */
    public function redirects(): HasMany
    {
        return $this->hasMany(CategoryRedirect::class);
    }

    /** Glina, Szycie or a voucher shelf: these stay where they are. */
    public function isTop(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Written in the language, and so is every shelf above it: a breadcrumb never shows a Polish name on /en/.
     */
    public function availableIn(string $locale): bool
    {
        return $this->hasTranslation($locale) && ($this->parent === null || $this->parent->availableIn($locale));
    }

    /**
     * An address the category had before (MA-351: „Kubki i filiżanki” is now „Kubki”) goes on to its new one,
     * with the query string, so a link in Google or someone's bookmark still lands on the shelf.
     */
    public function resolveRouteBinding($value, $field = null)
    {
        $category = $this->resolveTranslatedRouteBinding($value, $field);

        if ($category !== null || ($field ?? $this->getRouteKeyName()) !== 'slug') {
            return $category;
        }

        $redirect = CategoryRedirect::query()->where('locale', Locales::current())->where('slug', $value)->with('category')->first();

        if ($redirect?->category === null || ! $redirect->category->availableIn(Locales::current())) {
            return null;
        }

        $query = (string) request()->server('QUERY_STRING');

        throw new HttpResponseException(redirect()->to(route('shop.category', $redirect->category).($query !== '' ? '?'.$query : ''), 301));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'group' => CategoryGroup::class,
            'sort_order' => 'integer',
            'parent_id' => 'integer',
        ];
    }
}
