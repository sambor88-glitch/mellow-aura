<?php

namespace App\Modules\Settings\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * The „Karta produktu” card: sentences every product page uses when the product has none of its own —
 * how much a handmade piece may differ from its dimensions (also in §4 of the terms) and how to care for ceramics.
 */
class SaveProductCardRequest extends FormRequest
{
    public const KEYS = ['size_tolerance', 'care_rule_ceramics'];

    /** @var string */
    protected $errorBag = 'karta-produktu';

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
            'size_tolerance' => ['nullable', 'string', 'max:60'],
            'care_rule_ceramics' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'size_tolerance.max' => 'Różnicę wymiarów zmieszczę do :max znaków, np. 0,5 cm',
            'care_rule_ceramics.max' => 'Zdanie o pielęgnacji zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array<string, ?string>
     */
    public function settings(): array
    {
        return array_map(fn (mixed $value) => filled($value) ? trim((string) $value) : null, Arr::only($this->validated(), self::KEYS));
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.settings.edit').'#karta-produktu';
    }
}
