<?php

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\Occasion;
use App\Modules\Catalog\Enums\Recipient;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

#[Fillable([
    'slug', 'name', 'category_id', 'description', 'dimensions', 'care_note', 'is_published',
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
     * Products with at least one piece left in any variant — the prototype's `live` list.
     */
    public function scopeInStock(Builder $query): void
    {
        $query->whereHas('variants', fn (Builder $variants) => $variants->where('stock', '>', 0));
    }

    /**
     * Variant that carries the headline price: the cheapest one.
     */
    public function headlineVariant(): ?ProductVariant
    {
        return $this->variants->sortBy('price_gross')->first();
    }

    public function hasPriceRange(): bool
    {
        return $this->variants->pluck('price_gross')->unique()->count() > 1;
    }

    public function url(): string
    {
        return url('/produkt/'.$this->slug);
    }
}
