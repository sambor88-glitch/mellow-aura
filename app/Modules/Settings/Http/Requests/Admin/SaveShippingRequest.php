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
            'shipping.*.required' => $price,
            'shipping.*.regex' => $price,
        ];
    }

    /**
     * Settings to save: amounts in grosze, each delivery option keeping its name and note.
     * An empty or zero threshold means no free shipping.
     *
     * @return array{free_shipping_threshold: ?int, shipping_methods: list<mixed>}
     */
    public function settings(Settings $settings): array
    {
        $threshold = $this->validated('free_shipping_threshold');
        $threshold = filled($threshold) ? Money::parse((string) $threshold) : 0;
        $prices = (array) $this->validated('shipping', []);

        return [
            'free_shipping_threshold' => $threshold > 0 ? $threshold : null,
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
