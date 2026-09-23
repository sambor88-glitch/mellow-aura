<?php

namespace App\Modules\Shared\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A price an offer from a panel price list had from a given moment.
 */
#[Table('offer_price_history', timestamps: false)]
#[Fillable(['offer', 'price_gross', 'valid_from'])]
class OfferPriceHistory extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price_gross' => 'integer',
            'valid_from' => 'datetime',
        ];
    }
}
