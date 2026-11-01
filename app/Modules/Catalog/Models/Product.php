<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductFactory;
use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Enums\Dimension;
use App\Modules\Catalog\Enums\FoodContact;
use App\Modules\Catalog\Enums\GoogleCategory;
use App\Modules\Catalog\Enums\VariantKind;
use App\Modules\Localization\Support\Locales;
use App\Modules\Shared\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'slug', 'name', 'piece_name', 'category_id', 'description', 'story', 'seo_description', 'dimensions', 'care_note',
    'food_contact', 'deviation', 'size_tolerance', 'safety_warnings', 'google_category', 'show_in_google',
    'is_published', 'is_one_off', 'is_exact_piece', 'variant_kind', 'euro_from_rate', 'sort_order', 'stamp_enabled', 'workshop_code', 'occasions', 'recipients',
])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, HasTranslations, InteractsWithMedia;

    public const MODEL_COLLECTION = 'model';

    /** Read in the language of the page; the Polish text lives in the columns themselves. */
    protected array $translatable = ['slug', 'name', 'piece_name', 'description', 'story', 'seo_description', 'care_note', 'deviation', 'size_tolerance', 'safety_warnings'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->acceptsMimeTypes(['image/webp', 'image/jpeg', 'image/png']);

        // The scanned piece for „Zobacz u siebie” (MA-310): one binary glTF file. iPhones get a USDZ copy made in the browser.
        $this->addMediaCollection(self::MODEL_COLLECTION)->singleFile();
    }

    /** The 3D model shown in augmented reality, or null when the product has none. */
    public function arModel(): ?Media
    {
        return $this->getFirstMedia(self::MODEL_COLLECTION);
    }

    /**
     * Smaller copies, made right after upload without waiting for a queue worker. Views ask for them
     * through getAvailableUrl(), so a photo whose copies are not made yet still shows at full size.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        // Not cut: the frame on the page cuts it, around the point set in the panel (FocalPoint).
        $this->addMediaConversion('thumb')->fit(Fit::Max, 400, 500)->format('webp')->quality(80)->nonQueued()->performOnCollections('images');
        $this->addMediaConversion('phone')->fit(Fit::Max, 720, 900)->format('webp')->quality(80)->nonQueued()->performOnCollections('images');
        $this->addMediaConversion('card')->fit(Fit::Max, 1200, 1500)->format('webp')->quality(82)->nonQueued()->performOnCollections('images');
    }

    /**
     * Published products with at least one visible variant on the shelf or without stock tracking — on a page in another
     * language, only the ones written in it and priced in its currency.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('is_published', true)
            ->whereHas('variants', fn (Builder $variants) => $variants->visible()->inStock()->pricedIn())
            ->translatedInto();
    }

    /**
     * What a product card and a product page read, in the language and the currency of the page: on /en/ only the
     * variants with a euro price, so no card ever shows a złoty amount under a euro sign. Hidden variants never.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function withShelf(Builder $query): void
    {
        $query->with(self::shelfRelations());
    }

    /**
     * The same for a product already in hand, e.g. from a route.
     */
    public function loadShelf(): static
    {
        return $this->load(self::shelfRelations());
    }

    /**
     * Translations only where they are read: a Polish page reads the Polish columns. They load inside the variants'
     * own query, since a separate „variants.translations” would load every variant again, priced or not.
     *
     * @return array<int|string, mixed>
     */
    private static function shelfRelations(): array
    {
        $translated = Locales::current() !== Locales::default();

        return [
            'variants' => fn ($variants) => $variants->visible()->pricedIn()->orderBy('id')->when($translated, fn ($variants) => $variants->with('translations')),
            'category' => fn ($category) => $category->when($translated, fn ($category) => $category->with('translations')),
            'media',
        ];
    }

    /** Whether the product has a page in $locale: written in its language and priced in its currency. */
    public function availableIn(string $locale): bool
    {
        return $this->hasTranslation($locale)
            && ($locale === Locales::default() || $this->variants()->visible()->pricedIn(Locales::currency($locale))->exists());
    }

    /**
     * A voucher for a workshop or an amount: bought like a product, and issued with its own code
     * once the payment comes in.
     */
    public function isVoucher(): bool
    {
        return $this->category?->group === CategoryGroup::Workshops;
    }

    /**
     * Filled dimensions in panel order, e.g. ['Wysokość' => '24 cm'].
     *
     * @return array<string, string>
     */
    public function dimensionLabels(): array
    {
        $values = $this->dimensions ?? [];

        return collect(Dimension::cases())
            ->filter(fn (Dimension $dimension) => trim((string) ($values[$dimension->value] ?? '')) !== '')
            ->mapWithKeys(fn (Dimension $dimension) => [
                $dimension->label() => trim((string) $values[$dimension->value]).' '.$dimension->unit(),
            ])
            ->all();
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * The other shelves the product shows on („Pokaż też w”, MA-351). Its address, breadcrumbs and the Google feed follow
     * the main one only.
     *
     * @return BelongsToMany<Category, $this>
     */
    public function alsoInCategories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_product');
    }

    /**
     * Every shelf the product stands on: the main one first.
     *
     * @return list<int>
     */
    public function shelfIds(): array
    {
        return array_values(array_unique([$this->category_id, ...$this->alsoInCategories->modelKeys()]));
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'dimensions' => 'array',
            'food_contact' => FoodContact::class,
            'google_category' => GoogleCategory::class,
            'show_in_google' => 'boolean',
            'is_published' => 'boolean',
            'is_one_off' => 'boolean',
            'is_exact_piece' => 'boolean',
            'variant_kind' => VariantKind::class,
            'euro_from_rate' => 'boolean',
            'sort_order' => 'integer',
            'stamp_enabled' => 'boolean',
            'occasions' => 'array',
            'recipients' => 'array',
        ];
    }
}
