<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Database\Factories\ProductFactory;
use App\Modules\Catalog\Enums\Dimension;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Fillable([
    'slug', 'name', 'category_id', 'description', 'seo_description', 'dimensions', 'care_note',
    'is_published', 'is_one_off', 'sort_order', 'stamp_enabled', 'occasions', 'recipients',
])]
class Product extends Model implements HasMedia
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory, InteractsWithMedia;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('images')->acceptsMimeTypes(['image/webp', 'image/jpeg', 'image/png']);
    }

    /**
     * Smaller copies, made right after upload without waiting for a queue worker. Views ask for them
     * through getAvailableUrl(), so a photo whose copies are not made yet still shows at full size.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')->fit(Fit::Crop, 300, 375)->format('webp')->quality(80)->nonQueued();
        $this->addMediaConversion('card')->fit(Fit::Max, 1200, 1500)->format('webp')->quality(82)->nonQueued();
    }

    /**
     * Published products with at least one variant on the shelf or without stock tracking.
     *
     * @param  Builder<Product>  $query
     */
    #[Scope]
    protected function live(Builder $query): void
    {
        $query->where('is_published', true)->whereHas('variants', fn (Builder $variants) => $variants->inStock());
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
            'is_published' => 'boolean',
            'is_one_off' => 'boolean',
            'sort_order' => 'integer',
            'stamp_enabled' => 'boolean',
            'occasions' => 'array',
            'recipients' => 'array',
        ];
    }
}
