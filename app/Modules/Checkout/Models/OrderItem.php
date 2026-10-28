<?php

namespace App\Modules\Checkout\Models;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Checkout\Support\PieceToken;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'product_variant_id', 'product_name', 'variant_label', 'quantity', 'missing_quantity', 'is_made_to_order', 'unit_price_gross', 'currency', 'vat_rate', 'vat_exemption',
    'custom_text', 'custom_glaze', 'accepted_deviation', 'recipient_name', 'sender_name', 'dedication',
    'fired_on', 'glaze', 'plant',
])]
class OrderItem extends Model
{
    /** Every item gets the token of its page when it is saved; only a piece (getsCertificate) opens one. */
    protected static function booted(): void
    {
        static::creating(function (OrderItem $item) {
            $item->page_token ??= PieceToken::generate();
        });
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return HasMany<Certificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    /**
     * A handmade piece goes into the parcel with a certificate of uniqueness. A mug from the configurator has
     * no product in the shop but is one; a voucher and gift wrapping are not pieces.
     */
    public function getsCertificate(): bool
    {
        return $this->variant !== null ? ! $this->variant->product->isVoucher() : $this->custom_text !== null;
    }

    /** The page behind the QR code on the certificate and the card in the parcel (MA-234). */
    public function pageUrl(): string
    {
        return route('piece.show', $this->page_token);
    }

    public function total(): int
    {
        return $this->unit_price_gross * $this->quantity;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'missing_quantity' => 'integer',
            'is_made_to_order' => 'boolean',
            'unit_price_gross' => 'integer',
            'vat_rate' => 'decimal:2',
            'fired_on' => 'date',
        ];
    }
}
