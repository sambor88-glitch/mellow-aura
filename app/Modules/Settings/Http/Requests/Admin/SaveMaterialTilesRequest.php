<?php

namespace App\Modules\Settings\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;

/**
 * The material tiles for „O mnie”: glina, jedwab, złoto, and facts such as the firing temperature or
 * the dishwasher. A tile without a description is saved but does not show on the site.
 */
class SaveMaterialTilesRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'materialy';

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
            'tiles' => ['nullable', 'array', 'max:20'],
            'tiles.*.title' => ['required', 'string', 'max:40'],
            'tiles.*.text' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'tiles.max' => 'Zmieszczę do :max kafelków',
            'tiles.*.title.required' => 'Nazwij kafelek — np. Glina',
            'tiles.*.title.max' => 'Nazwę kafelka zmieszczę do :max znaków',
            'tiles.*.text.max' => 'Opis zmieszczę do :max znaków — wystarczą jedno, dwa zdania',
        ];
    }

    /**
     * @return array{material_tiles: list<array{title: string, text: ?string}>}
     */
    public function settings(): array
    {
        return [
            'material_tiles' => array_map(fn (array $tile) => [
                'title' => trim((string) $tile['title']),
                'text' => filled($tile['text'] ?? null) ? trim((string) $tile['text']) : null,
            ], array_values((array) $this->validated('tiles', []))),
        ];
    }

    protected function prepareForValidation(): void
    {
        $tiles = collect((array) $this->input('tiles', []))
            ->filter(fn (mixed $tile) => is_array($tile) && ! ($tile['remove'] ?? false))
            // A row left empty is a spare slot for a new tile.
            ->reject(fn (array $tile) => blank($tile['title'] ?? null) && blank($tile['text'] ?? null))
            ->map(fn (array $tile) => Arr::only($tile, ['title', 'text']))
            ->values()
            ->all();

        $this->merge(['tiles' => $tiles]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.settings.edit').'#materialy';
    }
}
