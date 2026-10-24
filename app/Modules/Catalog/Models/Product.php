<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\Dimension;
use App\Modules\Catalog\Enums\Occasion;
use App\Modules\Catalog\Enums\Recipient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'slug', 'name', 'category_id', 'description', 'seo_description', 'dimensions', 'care_note', 'is_published',
    'is_one_off', 'sort_order', 'stamp_enabled', 'occasions', 'recipients',
])]
class Product extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia;

    public const GALLERY = 'gallery';

    protected function casts(): array
    {
        return [
            'dimensions' => 'array',
            'is_published' => 'boolean',
            'is_one_off' => 'boolean',
            'stamp_enabled' => 'boolean',
            'occasions' => AsEnumCollection::of(Occasion::class),
            'recipients' => AsEnumCollection::of(Recipient::class),
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(self::GALLERY);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * On the shelf: published and at least one variant in stock. A sold-out
     * product keeps its page (200, OutOfStock) but leaves the lists.
     */
    public function scopeAvailable(Builder $query): void
    {
        $query->published()->whereHas('variants', fn (Builder $variants) => $variants->where('stock', '>', 0));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * @return Collection<int, Media>
     */
    public function photos(): Collection
    {
        return $this->getMedia(self::GALLERY);
    }

    public function coverUrl(): ?string
    {
        return $this->photos()->first()?->getUrl();
    }

    public function isAvailable(): bool
    {
        return $this->variants->contains(fn (ProductVariant $variant) => $variant->stock > 0);
    }

    public function totalStock(): int
    {
        return (int) $this->variants->sum('stock');
    }

    public function priceFrom(): int
    {
        return (int) $this->variants->min('price_gross');
    }

    public function hasSinglePrice(): bool
    {
        return $this->variants->pluck('price_gross')->unique()->count() <= 1;
    }

    /**
     * The variant shown with a crossed-out price on the card.
     */
    public function discountedVariant(): ?ProductVariant
    {
        return $this->variants->first(fn (ProductVariant $variant) => $variant->isDiscounted() && $variant->stock > 0);
    }

    /**
     * The product's own note, otherwise the rule of its category.
     */
    public function careNote(): ?string
    {
        $note = $this->care_note ?? $this->category?->default_care_note;

        return filled($note) ? $note : null;
    }

    /**
     * @return array<int, array{label: string, value: string}>
     */
    public function dimensionList(): array
    {
        $list = [];

        foreach (Dimension::cases() as $dimension) {
            $value = $this->dimensions[$dimension->value] ?? null;

            if (filled($value)) {
                $list[] = ['label' => $dimension->label(), 'value' => $dimension->format($value)];
            }
        }

        return $list;
    }
}
