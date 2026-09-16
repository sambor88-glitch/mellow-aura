<?php

namespace App\Modules\Settings\Http\Requests\Admin;

use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The „Dostawa i opłaty” card: the free-shipping threshold, the price of each delivery option and how many
 * working days an item from the shelf takes to leave the studio.
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
            'dispatch_days_min' => ['nullable', 'integer', 'min:1', 'max:60'],
            'dispatch_days_max' => ['nullable', 'integer', 'min:1', 'max:60'],
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
            'dispatch_days_min.*' => 'Wpisz liczbę dni, np. 3',
            'dispatch_days_max.*' => 'Wpisz liczbę dni, np. 5',
        ];
    }

    /**
     * Settings to save: amounts in grosze, each delivery option keeping its name and note.
     * An empty or zero threshold means no free shipping, and an empty gift wrap price means no gift wrapping.
     *
     * @return array{free_shipping_threshold: ?int, gift_wrap_price?: ?int, dispatch_days_min?: ?int, dispatch_days_max?: ?int, shipping_methods: list<mixed>}
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
            ...($this->has('dispatch_days_min') || $this->has('dispatch_days_max') ? $this->dispatchDays() : []),
            'shipping_methods' => collect((array) $settings->get('shipping_methods', []))
                ->map(fn (mixed $method) => is_array($method) && array_key_exists((string) ($method['code'] ?? ''), $prices)
                    ? [...$method, 'price_gross' => Money::parse((string) $prices[$method['code']])]
                    : $method)
                ->values()
                ->all(),
        ];
    }

    /**
     * Both ends in order; with one of them empty, the other stands for both.
     *
     * @return array{dispatch_days_min: ?int, dispatch_days_max: ?int}
     */
    private function dispatchDays(): array
    {
        $days = array_map('intval', array_filter([$this->validated('dispatch_days_min'), $this->validated('dispatch_days_max')], 'filled'));

        return ['dispatch_days_min' => $days ? min($days) : null, 'dispatch_days_max' => $days ? max($days) : null];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.settings.edit').'#dostawa';
    }
}
