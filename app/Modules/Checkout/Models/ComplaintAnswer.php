<?php

namespace App\Modules\Checkout\Models;

use App\Modules\Checkout\Enums\ComplaintDecision;
use App\Modules\Checkout\Enums\ComplaintRemedy;
use App\Modules\Checkout\Enums\MediationConsent;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An answer to a complaint, kept with the letter exactly as it was e-mailed.
 */
#[Fillable(['order_id', 'order_number', 'name', 'email', 'received_on', 'decision', 'remedy', 'details', 'mediation', 'letter', 'emailed_at'])]
class ComplaintAnswer extends Model
{
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'decision' => ComplaintDecision::class,
            'remedy' => ComplaintRemedy::class,
            'mediation' => MediationConsent::class,
            'emailed_at' => 'datetime',
        ];
    }
}
