<?php

namespace App\Modules\Content\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * A post added by hand: Kasia's photo, the link to the post on Instagram and what the photo shows.
 */
class StoreInstagramPostRequest extends FormRequest
{
    // A bigger photo would not fit in PHP's memory while it is scaled down.
    private const MAX_SIDE = 8192;

    /** @var string */
    protected $errorBag = 'instagram-reczne';

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
            'photo' => ['bail', 'required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:15360', 'dimensions:max_width='.self::MAX_SIDE.',max_height='.self::MAX_SIDE],
            'permalink' => ['required', 'string', 'max:255', 'starts_with:https://www.instagram.com/'],
            'alt' => ['required', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $notPhoto = 'Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP';

        return [
            'photo.required' => 'Wybierz zdjęcie',
            'photo.uploaded' => 'Zdjęcie nie doszło — może jest za duże. Spróbuj mniejszego',
            'photo.file' => $notPhoto,
            'photo.mimetypes' => $notPhoto,
            'photo.max' => 'Zdjęcie jest za duże — do 15 MB',
            'photo.dimensions' => 'Zdjęcie jest za duże — zmniejsz je do '.self::MAX_SIDE.' px na dłuższym boku',
            'permalink.required' => 'Wklej link do posta na Instagramie',
            'permalink.max' => 'Ten link jest za długi — skopiuj go z Instagrama jeszcze raz',
            'permalink.starts_with' => 'Wklej link do posta, zaczyna się od https://www.instagram.com/',
            'alt.required' => 'Napisz krótko, co widać na zdjęciu — przeczyta to osoba niewidoma',
            'alt.max' => 'Opis zmieszczę do :max znaków',
        ];
    }

    public function permalink(): string
    {
        return trim((string) $this->validated('permalink'));
    }

    public function alt(): string
    {
        return trim((string) $this->validated('alt'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'permalink' => filled($link = $this->input('permalink')) ? trim((string) $link) : null,
            'alt' => filled($alt = $this->input('alt')) ? trim((string) $alt) : null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.content.edit').'#instagram-reczne';
    }
}
