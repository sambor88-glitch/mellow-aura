<?php

namespace App\Modules\Catalog\Http\Requests\Admin;

use App\Modules\Catalog\Support\GlbFile;
use Closure;
use Illuminate\Http\UploadedFile;

/**
 * Rules for the 3D model of a product (MA-310): one binary glTF file (.glb), as RealityScan, Polycam or Blender
 * export it. The file is checked by its header, not only by its name.
 */
class ModelRules
{
    public const MAX_MEGABYTES = 20;

    private const NOT_MODEL = 'Tego pliku nie dodam — wybierz model 3D w formacie GLB';

    /**
     * @return array<string, list<mixed>>
     */
    public static function rules(): array
    {
        return [
            'model' => [
                'bail', 'required', 'file', 'extensions:glb', 'max:'.self::MAX_MEGABYTES * 1024,
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! GlbFile::isGlb($value->getRealPath())) {
                        $fail(self::NOT_MODEL);
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function messages(): array
    {
        return [
            'model.required' => 'Wybierz plik z modelem 3D (GLB)',
            'model.uploaded' => 'Plik nie doszedł — może jest za duży. Zmniejsz go do '.self::MAX_MEGABYTES.' MB',
            'model.file' => self::NOT_MODEL,
            'model.extensions' => self::NOT_MODEL,
            'model.max' => 'Model jest za duży — jeden plik do '.self::MAX_MEGABYTES.' MB, najlepiej do 5 MB',
        ];
    }
}
