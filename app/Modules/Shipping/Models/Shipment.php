<?php

namespace App\Modules\Shipping\Models;

use App\Modules\Checkout\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One parcel sent through ShipX (see the migration).
 */
class Shipment extends Model
{
    public const LOCKER = 'inpost_locker_standard';

    public const COURIER = 'inpost_courier_standard';

    /** The three sizes, as ShipX names the locker templates, with what Kasia reads in the panel. */
    public const SIZES = [
        'small' => 'A — mała, do 8 cm wysokości',
        'medium' => 'B — średnia, do 19 cm',
        'large' => 'C — duża, do 41 cm',
    ];

    /** ShipX's statuses before the parcel has a label to print. */
    private const PREPARING = ['created', 'offers_prepared', 'offer_selected'];

    protected $fillable = ['order_id', 'shipx_id', 'service', 'size', 'target_point', 'tracking_number', 'status'];

    public static function latestFor(Order $order): ?self
    {
        return self::query()->where('order_id', $order->id)->latest('id')->first();
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPreparing(): bool
    {
        return in_array($this->status, self::PREPARING, true);
    }

    public function sizeLabel(): string
    {
        return self::SIZES[$this->size] ?? $this->size;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['shipx_id' => 'integer'];
    }
}
