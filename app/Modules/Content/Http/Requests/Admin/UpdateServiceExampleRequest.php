<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Models\ServiceExample;
use App\Modules\Shared\Support\FocalPoint;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The caption, descriptions and photo frames of a pair already on the page. Mistakes come back under that pair.
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
            ...FocalPoint::rules('focus.before'),
            ...FocalPoint::rules('focus.after'),
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
            ...FocalPoint::messages('focus.before'),
            ...FocalPoint::messages('focus.after'),
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

    /**
     * The point kept in the 4:5 frame, per photo; a photo without sliders on the form is left as it is.
     *
     * @return array<'before'|'after', array{x: int, y: int}>
     */
    public function focus(): array
    {
        return collect((array) $this->validated('focus', []))
            ->only(['before', 'after'])
            ->map(fn (array $point) => ['x' => (int) $point['x'], 'y' => (int) $point['y']])
            ->all();
    }

    protected function getRedirectUrl(): string
    {
        /** @var ServiceExample $example */
        $example = $this->route('example');

        return route('admin.services.edit', $example->service->slug()).'#para-'.$example->id;
    }
}
