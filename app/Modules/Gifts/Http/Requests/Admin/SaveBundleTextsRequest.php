<?php

namespace App\Modules\Gifts\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The sentences on /zestawy-prezentowe. An empty heading falls back to „Zestawy prezentowe”, an empty
 * paragraph is not shown.
 */
class SaveBundleTextsRequest extends FormRequest
{
    public const KEYS = ['text_bundles_heading', 'text_bundles_lead', 'text_gift_wrap_heading', 'text_gift_wrap_lead'];

    /** @var string */
    protected $errorBag = 'teksty';

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
            'text_bundles_heading' => ['nullable', 'string', 'max:120'],
            'text_bundles_lead' => ['nullable', 'string', 'max:400'],
            'text_gift_wrap_heading' => ['nullable', 'string', 'max:80'],
            'text_gift_wrap_lead' => ['nullable', 'string', 'max:400'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.max' => 'Ten tekst zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array<string, ?string>
     */
    public function settings(): array
    {
        return collect(self::KEYS)
            ->mapWithKeys(fn (string $key) => [$key => filled($value = $this->validated($key)) ? str_replace("\r\n", "\n", trim($value)) : null])
            ->all();
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.gifts.edit').'#teksty';
    }
}
