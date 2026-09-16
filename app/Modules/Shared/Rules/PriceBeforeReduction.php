<?php

namespace App\Modules\Shared\Rules;

use App\Modules\Shared\Support\Money;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * „Cena przed obniżką” next to a row's price, e.g. variants.0.compare_at next to variants.0.price: an amount
 * higher than the price, or nothing.
 */
class PriceBeforeReduction implements DataAwareRule, ValidationRule
{
    private const AMOUNT = '/^\d{1,5}([.,]\d{1,2})?$/';

    /** @var array<string, mixed> */
    private array $data = [];

    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! preg_match(self::AMOUNT, (string) $value)) {
            $fail('Wpisz cenę sprzed obniżki, np. 260 — albo zostaw puste pole');

            return;
        }

        $price = data_get($this->data, Str::beforeLast($attribute, '.').'.price');

        if (preg_match(self::AMOUNT, (string) $price) && Money::parse((string) $value) <= Money::parse((string) $price)) {
            $fail('Cena przed obniżką musi być wyższa niż obecna — albo zostaw puste pole');
        }
    }
}
