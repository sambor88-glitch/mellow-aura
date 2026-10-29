<?php

namespace App\Modules\Catalog\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Someone waiting for a sold-out size to come back (MA-266). She agreed to this one mail and nothing else:
 * Catalog\Actions\SendRestockNotices sends it and removes the row.
 */
#[Fillable(['product_variant_id', 'email', 'locale', 'token'])]
class StockNotification extends Model
{
    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }
}
