<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use Illuminate\Http\UploadedFile;

/**
 * Rules for photos sent from the panel, shared by the new product form and the photo strip under each product.
 */
class PhotoRules
{
    public const MAX_PHOTOS = 10;

    private const MAX_KILOBYTES = 15 * 1024;

    // A bigger photo would not fit in PHP's memory while it is scaled down.
    private const MAX_SIDE = 8192;

    /**
     * @return array<string, list<string>>
     */
    public static function rules(bool $required): array
    {
        return [
            'photos' => [$required ? 'required' : 'nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => [
                'bail', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::MAX_KILOBYTES,
                'dimensions:max_width='.self::MAX_SIDE.',max_height='.self::MAX_SIDE,
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        $choose = 'Wybierz zdjęcia z telefonu albo komputera';
        $notPhoto = 'Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP';

        return [
            'photos.required' => $choose,
            'photos.array' => $choose,
            'photos.max' => 'Naraz dodasz do :max zdjęć',
            'photos.*.uploaded' => 'Zdjęcie nie doszło — może jest za duże. Spróbuj mniejszego',
            'photos.*.file' => $notPhoto,
            'photos.*.mimetypes' => $notPhoto,
            'photos.*.max' => 'Zdjęcie jest za duże — jedno do 15 MB',
            'photos.*.dimensions' => 'Zdjęcie jest za duże — zmniejsz je do '.self::MAX_SIDE.' px na dłuższym boku',
        ];
    }

    /**
     * Limits the page checks before sending. PHP drops a request over post_max_size without a word,
     * so a too-big upload gets a message instead of an empty form.
     *
     * @return array{maxFiles: int, maxFileBytes: int, maxTotalBytes: int}
     */
    public static function limits(): array
    {
        return [
            'maxFiles' => self::MAX_PHOTOS,
            'maxFileBytes' => (int) min(self::MAX_KILOBYTES * 1024, UploadedFile::getMaxFilesize()),
            'maxTotalBytes' => self::iniBytes((string) ini_get('post_max_size')),
        ];
    }

    private static function iniBytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        } ?: PHP_INT_MAX;
    }
}
