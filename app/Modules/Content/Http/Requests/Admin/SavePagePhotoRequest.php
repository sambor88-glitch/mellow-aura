<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Support\PagePhotos;
use App\Modules\Shared\Support\FocalPoint;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The point of a photo that stays in view (on a phone too, where the place has a second one), Kasia's description
 * of her own photo and, around the hero frame, the product the photo leads to — for one place of „Zdjęcia stron”.
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
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
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
            'product_id.*' => 'Nie ma już takiego produktu — wybierz inny z listy',
        ];
    }

    /**
     * Only what the place has: a second point only where PagePhotos gives one, a description only where the photo is not
     * decoration, a product only where the photo can lead to one.
     *
     * @return array{focus: ?array{x: int, y: int}, focus_phone: ?array{x: int, y: int}, alt: ?string, product_id: ?int}
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
            'product_id' => ($place['product'] ?? false) && filled($this->validated('product_id')) ? (int) $this->validated('product_id') : null,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.page-photos.edit').'#'.$this->route('place');
    }
}
