<?php

namespace App\Modules\MugConfigurator\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The photo under the customer's text. The limits match product photos.
 */
class SaveMugPhotoRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'zdjecie';

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
            'photo' => ['bail', 'required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.(15 * 1024), 'dimensions:max_width=8192,max_height=8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $notPhoto = 'Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP';

        return [
            'photo.required' => 'Wybierz zdjęcie z telefonu albo komputera',
            'photo.uploaded' => 'Zdjęcie nie doszło — może jest za duże. Spróbuj mniejszego',
            'photo.file' => $notPhoto,
            'photo.mimetypes' => $notPhoto,
            'photo.max' => 'Zdjęcie jest za duże — do 15 MB',
            'photo.dimensions' => 'Zdjęcie jest za duże — zmniejsz je do 8192 px na dłuższym boku',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.mug.edit').'#zdjecie';
    }
}
