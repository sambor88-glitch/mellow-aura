<?php

namespace App\Modules\Checkout\Support;

use App\Modules\Localization\Support\Locales;
use App\Modules\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Lang;

/**
 * Delivery options from the panel (shipping_methods), in the currency of the page. Once the products reach the
 * free-shipping threshold every option is free, and a method priced at 0, like pickup at the studio, always is.
 *
 * Złoty prices are price_gross; a euro page offers only the methods with a euro price (price_eur) and an English
 * name in checkout::shipping. The free-shipping threshold is Polish and in złoty only.
 */
class ShippingMethods
{
    /** An order of vouchers sent as PDFs: nothing goes in a parcel, the e-mail brings them. */
    public const EMAIL = 'email';

    public function __construct(private Settings $settings) {}

    /**
     * @return Collection<string, array{code: string, label: string, note: ?string, price_gross: int, price: int}>
     */
    public function all(): Collection
    {
        $home = Locales::currency() === Locales::defaultCurrency();

        return collect((array) $this->settings->get('shipping_methods', []))
            ->filter(fn (mixed $method) => is_array($method) && filled($method['code'] ?? null) && filled($method['label'] ?? null))
            ->filter(fn (array $method) => $home || (is_numeric($method['price_eur'] ?? null) && Lang::has('checkout::shipping.'.$method['code'].'.label')))
            ->mapWithKeys(fn (array $method) => [$method['code'] => [
                'code' => $method['code'],
                'label' => $home ? $method['label'] : __('checkout::shipping.'.$method['code'].'.label'),
                'note' => $home ? ($method['note'] ?? null) : (__('checkout::shipping.'.$method['code'].'.note') ?: null),
                'price_gross' => (int) ($method['price_gross'] ?? 0),
                // In the currency of the page.
                'price' => (int) ($home ? ($method['price_gross'] ?? 0) : $method['price_eur']),
            ]]);
    }

    /**
     * The name of the way an order is delivered, also for an order whose method was later removed from the panel.
     */
    public function label(string $code): string
    {
        return $code === self::EMAIL ? __('checkout::checkout.delivery_email') : ($this->all()->get($code)['label'] ?? $code);
    }

    /** The free-shipping threshold in the currency of the page: only złoty has one. */
    public function freeFrom(): int
    {
        return Locales::currency() === Locales::defaultCurrency() ? (int) $this->settings->get('free_shipping_threshold', 0) : 0;
    }

    /**
     * What delivery costs when the products are worth $subtotal, both in the currency of the page.
     */
    public function cost(string $code, int $subtotal): int
    {
        if ($this->freeFrom() > 0 && $subtotal >= $this->freeFrom()) {
            return 0;
        }

        return $this->all()->get($code)['price'] ?? 0;
    }
}
