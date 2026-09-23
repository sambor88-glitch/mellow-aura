<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Models\ServiceExample;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The caption and descriptions of a pair already on the page. Mistakes come back under that pair.
 */
class UpdateServiceExampleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = 'para-'.$this->route('example')->id;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'caption' => ['nullable', 'string', 'max:160'],
            'before_alt' => ['nullable', 'string', 'max:160'],
            'after_alt' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'caption.max' => 'Podpis zmieszczę do :max znaków',
            'before_alt.max' => 'Opis zmieszczę do :max znaków',
            'after_alt.max' => 'Opis zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array{caption: ?string, before_alt: ?string, after_alt: ?string}
     */
    public function texts(): array
    {
        return collect(['caption', 'before_alt', 'after_alt'])
            ->mapWithKeys(fn (string $field) => [$field => filled($value = $this->validated($field)) ? trim($value) : null])
            ->all();
    }

    protected function getRedirectUrl(): string
    {
        /** @var ServiceExample $example */
        $example = $this->route('example');

        return route('admin.services.edit', $example->service->slug()).'#para-'.$example->id;
    }
}
