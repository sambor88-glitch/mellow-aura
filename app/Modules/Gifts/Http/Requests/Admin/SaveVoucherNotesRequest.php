<?php

namespace App\Modules\Gifts\Http\Requests\Admin;

use App\Modules\Catalog\Enums\CategoryGroup;
use App\Modules\Catalog\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The short workshop description printed on a voucher, one for each voucher in the shop:
 * what to expect, what you'll do, what you'll take home and how to get ready. An empty field
 * does not show on the voucher, so an amount voucher simply leaves all four empty.
 */
class SaveVoucherNotesRequest extends FormRequest
{
    public const FIELDS = ['expect', 'activities', 'takeaway', 'preparation'];

    public const MAX = 160;

    /** @var string */
    protected $errorBag = 'opis-warsztatu';

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
            'notes' => ['nullable', 'array'],
            'notes.*' => ['array'],
            'notes.*.*' => ['nullable', 'string', 'max:'.self::MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.*.*.max' => 'Zmieszczę do :max znaków — jedno, dwa krótkie zdania',
        ];
    }

    /**
     * Only vouchers that exist in the shop, only filled fields.
     *
     * @return array{voucher_workshop_notes: array<string, array<string, string>>}
     */
    public function settings(): array
    {
        $slugs = Product::query()->whereRelation('category', 'group', CategoryGroup::Workshops->value)->pluck('slug')->all();
        $notes = [];

        foreach ($slugs as $slug) {
            $filled = collect(self::FIELDS)
                ->mapWithKeys(fn (string $field) => [$field => trim((string) data_get($this->validated('notes', []), $slug.'.'.$field))])
                ->filter(fn (string $text) => $text !== '')
                ->all();

            if ($filled) {
                $notes[$slug] = $filled;
            }
        }

        return ['voucher_workshop_notes' => $notes];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.gifts.edit').'#opis-warsztatu';
    }
}
