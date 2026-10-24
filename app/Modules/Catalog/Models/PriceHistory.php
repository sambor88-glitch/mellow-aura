<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table('price_history')]
#[WithoutTimestamps]
#[Fillable(['product_variant_id', 'price_gross', 'valid_from'])]
class PriceHistory extends Model
{
    protected function casts(): array
    {
        return [
            'price_gross' => 'integer',
            'valid_from' => 'datetime',
        ];
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
