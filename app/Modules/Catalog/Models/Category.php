<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Kind of item — Kasia edits names and order in the panel.
 */
#[Fillable(['slug', 'name', 'intro', 'default_care_note', 'sort_order', 'seo_title', 'seo_description'])]
class Category extends Model
{
    use HasFactory;

    /**
     * Product page titles: "{name} — {tagline} | MellowAura" (spec, section 2).
     * Vouchers carry only the name and the brand.
     */
    private const PRODUCT_TITLE_TAGLINES = [
        'jedwab' => 'rękodzieło z jedwabiu',
        'kosmetyczki-i-piorniki' => 'rękodzieło z Krakowa',
        'vouchery' => null,
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function productTitleTagline(): ?string
    {
        return array_key_exists($this->slug, self::PRODUCT_TITLE_TAGLINES)
            ? self::PRODUCT_TITLE_TAGLINES[$this->slug]
            : 'ceramika handmade';
    }
}
