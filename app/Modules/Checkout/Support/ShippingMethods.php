<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Settings\Settings;
use Illuminate\Support\Collection;

/**
 * Delivery options from the panel (shipping_methods). Once the products reach the free-shipping
 * threshold every option is free, and a method priced at 0, like pickup at the studio, always is.
 */
class ShippingMethods
{
    /** An order of vouchers sent as PDFs: nothing goes in a parcel, the e-mail brings them. */
    public const EMAIL = 'email';

    public function __construct(private Settings $settings) {}

    /**
     * @return Collection<string, array{code: string, label: string, note: ?string, price_gross: int}>
     */
    public function all(): Collection
    {
        return collect((array) $this->settings->get('shipping_methods', []))
            ->filter(fn (mixed $method) => is_array($method) && filled($method['code'] ?? null) && filled($method['label'] ?? null))
            ->mapWithKeys(fn (array $method) => [$method['code'] => [
                'code' => $method['code'],
                'label' => $method['label'],
                'note' => $method['note'] ?? null,
                'price_gross' => (int) ($method['price_gross'] ?? 0),
            ]]);
    }

    /**
     * The name of the way an order is delivered, also for an order whose method was later removed from the panel.
     */
    public function label(string $code): string
    {
        return $code === self::EMAIL ? 'Mailem, w PDF' : ($this->all()->get($code)['label'] ?? $code);
    }

    public function freeFrom(): int
    {
        return (int) $this->settings->get('free_shipping_threshold', 0);
    }

    /**
     * What delivery costs when the products are worth $subtotal grosze.
     */
    public function cost(string $code, int $subtotal): int
    {
        if ($this->freeFrom() > 0 && $subtotal >= $this->freeFrom()) {
            return 0;
        }

        return $this->all()->get($code)['price_gross'] ?? 0;
    }
}
