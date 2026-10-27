<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Support\PagePhotos;
use App\Modules\Shared\Support\FocalPoint;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The point of a photo that stays in view (on a phone too, where the place has a second one) and Kasia's description
 * of her own photo, for one place of „Zdjęcia stron”.
 */
class SavePagePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->errorBag = 'zdjecie-'.$this->route('place');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...FocalPoint::rules('focus'),
            ...FocalPoint::rules('focus_phone'),
            'alt' => ['nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...FocalPoint::messages('focus'),
            ...FocalPoint::messages('focus_phone'),
            'alt.max' => 'Opis zmieszczę do :max znaków',
        ];
    }

    /**
     * Only what the place has: a second point only where PagePhotos gives one, a description only where the photo is not decoration.
     *
     * @return array{focus: ?array{x: int, y: int}, focus_phone: ?array{x: int, y: int}, alt: ?string}
     */
    public function changes(): array
    {
        $place = PagePhotos::place((string) $this->route('place'));
        $point = fn (string $field) => is_array($value = $this->validated($field))
            ? ['x' => (int) $value['x'], 'y' => (int) $value['y']]
            : null;

        return [
            'focus' => $point('focus'),
            'focus_phone' => ($place['phone'] ?? false) ? $point('focus_phone') : null,
            'alt' => ($place['decorative'] ?? false) ? null : (trim((string) $this->validated('alt')) ?: null),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.page-photos.edit').'#'.$this->route('place');
    }
}
