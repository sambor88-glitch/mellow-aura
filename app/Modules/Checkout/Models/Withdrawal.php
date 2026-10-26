<?php

namespace App\Modules\Checkout\Models;

use App\Modules\Checkout\Enums\WithdrawalScope;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A statement sent through „Odstąp od umowy tutaj” (Directive 2023/2673). It is kept as the customer
 * wrote it, with the moment it arrived, whether or not it matches an order.
 */
#[Fillable(['order_id', 'order_number', 'name', 'email', 'scope', 'items', 'submitted_at', 'handled_at'])]
class Withdrawal extends Model
{
    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * The statement in one sentence, as the confirmation quotes it.
     */
    public function statement(): string
    {
        return $this->scope === WithdrawalScope::Whole
            ? 'Ja, '.$this->name.', odstępuję od umowy zawartej w zamówieniu '.$this->order_number.'.'
            : 'Ja, '.$this->name.', odstępuję od umowy w części dotyczącej tych rzeczy z zamówienia '.$this->order_number.': '.$this->items;
    }

    /**
     * „16 września 2026, 18:32”
     */
    public function submittedAtLabel(): string
    {
        return $this->submitted_at->translatedFormat('j F Y, H:i');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => WithdrawalScope::class,
            'submitted_at' => 'datetime',
            'handled_at' => 'datetime',
        ];
    }
}
