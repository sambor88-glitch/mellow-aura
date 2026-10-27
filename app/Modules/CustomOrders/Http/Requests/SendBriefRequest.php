<?php

namespace App\Modules\CustomOrders\Http\Requests;

use App\Modules\CustomOrders\Enums\BriefKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * The custom order form: who, what it should be, how many and for when, the budget, the idea in her words and
 * up to six photos. The „website” field is hidden from people; a form that fills it in comes from a bot.
 */
class SendBriefRequest extends FormRequest
{
    public const MAX_PHOTOS = 6;

    // The page shrinks photos before sending; this is for a phone without JavaScript, which sends them whole.
    private const MAX_KILOBYTES = 20 * 1024;

    // A bigger photo would not fit in PHP's memory while it is scaled down. A 48 Mpx phone photo is 8064 px.
    private const MAX_SIDE = 8192;

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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'kind' => ['required', Rule::enum(BriefKind::class)],
            'quantity' => ['nullable', 'string', 'max:60'],
            'needed_by' => ['nullable', 'string', 'max:60'],
            'budget' => ['nullable', 'string', 'max:60'],
            'description' => ['required', 'string', 'max:3000'],
            'photos' => ['nullable', 'array', 'max:'.self::MAX_PHOTOS],
            'photos.*' => [
                'bail', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.self::MAX_KILOBYTES,
                'dimensions:max_width='.self::MAX_SIDE.',max_height='.self::MAX_SIDE,
            ],
            'website' => ['nullable', 'string'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $notPhoto = 'Tego pliku nie dodam — wybierz zdjęcie JPG, PNG albo WebP';
        $tooLong = 'Zmieszczę tu do :max znaków — resztę dopisz w opisie';

        return [
            'name.required' => 'Wpisz imię — żebym wiedziała, jak się do Ciebie zwracać',
            'name.max' => 'Imię zmieszczę do :max znaków',
            'email.required' => 'Wpisz e-mail — odpiszę na niego ze szkicem i wyceną',
            'email.email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
            'email.max' => 'Ten adres e-mail jest za długi — sprawdź go jeszcze raz',
            'phone.max' => 'Numer telefonu zmieszczę do :max znaków',
            'kind.required' => 'Wybierz, co to ma być — albo „Coś innego”',
            'kind.enum' => 'Wybierz, co to ma być, z listy',
            'quantity.max' => $tooLong,
            'needed_by.max' => $tooLong,
            'budget.max' => $tooLong,
            'description.required' => 'Opisz pomysł choć w kilku zdaniach — resztę dopytam',
            'description.max' => 'Opis zmieszczę do :max znaków — resztę dopiszesz w odpowiedzi',
            'photos.array' => $notPhoto,
            'photos.max' => 'Wyślę do :max zdjęć — wybierz te, na których najlepiej widać pomysł',
            'photos.*.uploaded' => 'Zdjęcie nie doszło — może jest za duże. Spróbuj mniejszego',
            'photos.*.file' => $notPhoto,
            'photos.*.mimetypes' => $notPhoto,
            'photos.*.max' => 'Zdjęcie jest za duże — jedno do 20 MB',
            'photos.*.dimensions' => 'Zdjęcie jest za duże — zmniejsz je do '.self::MAX_SIDE.' px na dłuższym boku',
        ];
    }

    public function kind(): BriefKind
    {
        return BriefKind::from((string) $this->validated('kind'));
    }

    /**
     * @return list<UploadedFile>
     */
    public function photos(): array
    {
        return array_values($this->file('photos', []));
    }

    public function isFromBot(): bool
    {
        return filled($this->input('website'));
    }

    /**
     * Limits the page checks before sending. PHP drops a request over post_max_size without a word,
     * so a too-big upload gets a message instead of an empty form.
     *
     * @return array{maxFiles: int, maxFileBytes: int, maxTotalBytes: int}
     */
    public static function limits(): array
    {
        $post = (string) ini_get('post_max_size');
        $number = (int) $post;
        $total = match (strtolower(substr(trim($post), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };

        return [
            'maxFiles' => self::MAX_PHOTOS,
            'maxFileBytes' => (int) min(self::MAX_KILOBYTES * 1024, UploadedFile::getMaxFilesize()),
            'maxTotalBytes' => $total ?: PHP_INT_MAX,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('custom-orders.index').'#formularz';
    }
}
