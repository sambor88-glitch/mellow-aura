<?php

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Models\StockNotification;
use App\Modules\Shared\Support\PersonalData;
use Illuminate\Support\Str;

/**
 * Puts an address on the list for a sold-out size (MA-266). The same address twice for the same size stays one
 * row: she gets one mail, and the page says the same thing either way, so nobody learns whose address is listed.
 */
class SubscribeToRestock
{
    public function __invoke(ProductVariant $variant, string $email, string $locale): StockNotification
    {
        return StockNotification::query()->firstOrCreate(
            ['product_variant_id' => $variant->id, 'email' => PersonalData::normalize($email)],
            ['locale' => $locale, 'token' => Str::random(40)],
        );
    }
}
