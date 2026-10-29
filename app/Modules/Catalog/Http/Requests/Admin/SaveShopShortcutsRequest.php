<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Support\ShopShortcuts;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The line under each shortcut card under the shop's products, in Polish and English. An empty field leaves the card
 * with its name only.
 */
class SaveShopShortcutsRequest extends FormRequest
{
    public const MAX = 60;

    /** @var string */
    protected $errorBag = 'skroty';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return collect(ShopShortcuts::ROUTES)->keys()
            ->flatMap(fn (string $label) => [
                "pl.$label" => ['nullable', 'string', 'max:'.self::MAX],
                "en.$label" => ['nullable', 'string', 'max:'.self::MAX],
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $message = 'Podpis zmieszczę do :max znaków — wystarczy kilka słów';

        return ['pl.*.max' => $message, 'en.*.max' => $message];
    }

    /**
     * Only the languages the form showed: with English switched off its lines stay as they were.
     *
     * @return array<string, ?string>
     */
    public function settings(): array
    {
        $data = $this->validated();

        return collect(['pl', 'en'])
            ->filter(fn (string $locale) => $this->has($locale))
            ->flatMap(fn (string $locale) => collect(ShopShortcuts::ROUTES)->keys()->mapWithKeys(fn (string $label) => [
                ShopShortcuts::noteKey($label, $locale) => filled($data[$locale][$label] ?? null) ? trim((string) $data[$locale][$label]) : null,
            ]))
            ->all();
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.categories.index').'#skroty';
    }
}
