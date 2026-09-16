<?php

namespace App\Modules\Settings\Http\Requests\Admin;

use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The „Dostawa i opłaty” card: the free-shipping threshold and the price of each delivery option.
 */
class SaveShippingRequest extends FormRequest
{
    private const PRICE = '/^\d{1,5}([.,]\d{1,2})?$/';

    /** @var string */
    protected $errorBag = 'dostawa';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'free_shipping_threshold' => ['nullable', 'regex:'.self::PRICE],
            'gift_wrap_price' => ['nullable', 'regex:'.self::PRICE],
            'shipping' => ['nullable', 'array'],
            'shipping.*' => ['required', 'regex:'.self::PRICE],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $price = 'Wpisz cenę, np. 16 albo 16,50 — za darmo wpisz 0';

        return [
            'free_shipping_threshold.regex' => 'Wpisz kwotę, np. 400 — albo zostaw puste pole',
            'gift_wrap_price.regex' => 'Wpisz cenę, np. 12 — puste pole wyłącza pakowanie na prezent',
            'shipping.*.required' => $price,
            'shipping.*.regex' => $price,
        ];
    }

    /**
     * Settings to save: amounts in grosze, each delivery option keeping its name and note.
     * An empty or zero threshold means no free shipping, and an empty gift wrap price means no gift wrapping.
     *
     * @return array{free_shipping_threshold: ?int, gift_wrap_price?: ?int, shipping_methods: list<mixed>}
     */
    public function settings(Settings $settings): array
    {
        $threshold = $this->validated('free_shipping_threshold');
        $threshold = filled($threshold) ? Money::parse((string) $threshold) : 0;
        $giftWrap = $this->validated('gift_wrap_price');
        $giftWrap = filled($giftWrap) ? Money::parse((string) $giftWrap) : 0;
        $prices = (array) $this->validated('shipping', []);

        return [
            'free_shipping_threshold' => $threshold > 0 ? $threshold : null,
            // Only the card with the field changes it.
            ...($this->has('gift_wrap_price') ? ['gift_wrap_price' => $giftWrap > 0 ? $giftWrap : null] : []),
            'shipping_methods' => collect((array) $settings->get('shipping_methods', []))
                ->map(fn (mixed $method) => is_array($method) && array_key_exists((string) ($method['code'] ?? ''), $prices)
                    ? [...$method, 'price_gross' => Money::parse((string) $prices[$method['code']])]
                    : $method)
                ->values()
                ->all(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.settings.edit').'#dostawa';
    }
}
