<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Enums\Service;
use Illuminate\Foundation\Http\FormRequest;

/**
 * A new „przed i po” pair: both photos at once, with an optional caption and descriptions.
 */
class StoreServiceExampleRequest extends FormRequest
{
    // A bigger photo would not fit in PHP's memory while it is scaled down.
    private const MAX_SIDE = 8192;

    /** @var string */
    protected $errorBag = 'przed-i-po';

    public function authorize(): bool
    {
        return true;
    }

    public function service(): Service
    {
        return Service::fromSlug((string) $this->route('service')) ?? abort(404);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $photo = ['bail', 'required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:15360', 'dimensions:max_width='.self::MAX_SIDE.',max_height='.self::MAX_SIDE];

        return [
            'before' => $photo,
            'after' => $photo,
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
        $notPhoto = 'Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP';

        return [
            'before.required' => 'Wybierz zdjęcie „przed”',
            'after.required' => 'Wybierz zdjęcie „po”',
            '*.uploaded' => 'Zdjęcie nie doszło — może jest za duże. Spróbuj mniejszego',
            '*.file' => $notPhoto,
            '*.mimetypes' => $notPhoto,
            'before.max' => 'Zdjęcie jest za duże — jedno do 15 MB',
            'after.max' => 'Zdjęcie jest za duże — jedno do 15 MB',
            '*.dimensions' => 'Zdjęcie jest za duże — zmniejsz je do '.self::MAX_SIDE.' px na dłuższym boku',
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
        return route('admin.services.edit', $this->route('service')).'#przed-i-po';
    }
}
