<?php

namespace App\Modules\Settings\Http\Requests\Admin;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;

/**
 * The picture above the signature in customer mails (MA-287). The picture shows 200 px wide at most, so anything under
 * MIN_WIDTH would look soft on a phone; anything bigger is scaled down when it is saved.
 */
class StoreMailSignatureImageRequest extends FormRequest
{
    public const MIN_WIDTH = 300;

    /** @var string */
    protected $errorBag = 'grafika-podpisu';

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
            'image' => [
                'bail',
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:'.(15 * 1024),
                'dimensions:max_width=8192,max_height=8192',
                function (string $attribute, mixed $value, Closure $fail) {
                    if ($value instanceof UploadedFile && ($value->dimensions()[0] ?? 0) < self::MIN_WIDTH) {
                        $fail('Grafika jest za mała i w mailu wyjdzie nieostra — wgraj co najmniej '.self::MIN_WIDTH.' px szerokości');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $notImage = 'Tego pliku nie dodam — wybierz grafikę JPG, PNG albo WebP';

        return [
            'image.required' => 'Wybierz grafikę z telefonu albo komputera',
            'image.uploaded' => 'Grafika nie doszła — może jest za duża. Spróbuj mniejszej',
            'image.file' => $notImage,
            'image.mimetypes' => $notImage,
            'image.max' => 'Grafika jest za duża — do 15 MB',
            'image.dimensions' => 'Grafika jest za duża — zmniejsz ją do 8192 px na dłuższym boku',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.mail-texts.edit').'#grafika-podpisu';
    }
}
