<?php

namespace App\Modules\Gifts\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A gift set in the panel. Every set has its own form, so mistakes come back in that form's error bag.
 */
class SaveBundleRequest extends FormRequest
{
    public const MAX_PARTS = 4;

    public function authorize(): bool
    {
        return true;
    }

    public function formKey(): string
    {
        $bundle = $this->route('bundle');

        return $bundle ? 'zestaw-'.$bundle->id : 'nowy-zestaw';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:600'],
            'discount_percent' => ['required', 'integer', 'min:0', 'max:90'],
            'parts' => ['required', 'array', 'min:2', 'max:'.self::MAX_PARTS],
            'parts.*' => ['integer', 'distinct', Rule::exists('product_variants', 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $discount = 'Rabat to liczba od 0 do 90 — bez znaku %';

        return [
            'name.required' => 'Nazwij zestaw — np. Poranek we dwoje',
            'name.max' => 'Nazwę zmieszczę do :max znaków',
            'description.max' => 'Opis zmieszczę do :max znaków',
            'discount_percent.required' => $discount,
            'discount_percent.integer' => $discount,
            'discount_percent.min' => $discount,
            'discount_percent.max' => $discount,
            'parts.required' => 'Wybierz co najmniej dwie rzeczy do zestawu',
            'parts.min' => 'Wybierz co najmniej dwie rzeczy do zestawu',
            'parts.max' => 'W zestawie zmieszczę do :max rzeczy',
            'parts.*.distinct' => 'Każda rzecz może być w zestawie raz',
            'parts.*.integer' => 'Tej rzeczy nie ma już w sklepie — wybierz inną',
            'parts.*.exists' => 'Tej rzeczy nie ma już w sklepie — wybierz inną',
        ];
    }

    /**
     * @return array{name: string, description: ?string, discount_percent: int, is_published: bool, parts: list<int>}
     */
    public function bundle(): array
    {
        $data = $this->validated();

        return [
            'name' => trim($data['name']),
            'description' => filled($data['description'] ?? null) ? trim($data['description']) : null,
            'discount_percent' => (int) $data['discount_percent'],
            'is_published' => $this->boolean('is_published'),
            'parts' => array_map(intval(...), array_values($data['parts'])),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = $this->formKey();

        $this->merge([
            'form' => $this->formKey(),
            // An empty choice is a spare slot, and "10%" means 10.
            'parts' => array_values(array_filter((array) $this->input('parts', []), fn (mixed $part) => filled($part))),
            'discount_percent' => is_string($discount = $this->input('discount_percent')) ? trim($discount, " \u{00A0}%") : $discount,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.gifts.edit').'#'.$this->formKey();
    }
}
