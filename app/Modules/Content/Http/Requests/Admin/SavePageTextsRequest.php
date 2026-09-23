<?php

namespace App\Modules\Content\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The sentences on the content pages. The panel form and the validation read the same list,
 * so a new text needs one entry here. An empty text is saved as null and does not show.
 */
class SavePageTextsRequest extends FormRequest
{
    /** @var list<array{title: string, route: ?string, fields: array<string, array{string, int, int}>}> label, rows, max length */
    public const PAGES = [
        ['title' => 'Strona główna', 'route' => 'home', 'fields' => [
            'text_home_kasia_heading' => ['„Cześć, jestem Kasia” — nagłówek', 2, 160],
            'text_home_kasia_paragraph' => ['„Cześć, jestem Kasia” — akapit', 4, 600],
        ]],
        ['title' => 'O mnie', 'route' => 'content.about', 'fields' => [
            'text_about_paragraph_1' => ['Pierwszy akapit — większym pismem', 4, 900],
            'text_about_paragraph_2' => ['Drugi akapit', 5, 900],
            'text_about_paragraph_3' => ['Trzeci akapit', 4, 900],
        ]],
        ['title' => 'Pracownia', 'route' => 'content.studio', 'fields' => [
            'text_studio_lead' => ['Zdanie pod zdjęciem — większym pismem', 3, 400],
            'text_studio_paragraph_1' => ['Pierwszy akapit', 4, 700],
            'text_studio_paragraph_2' => ['Drugi akapit', 4, 700],
        ]],
        ['title' => 'Zamówienia indywidualne', 'route' => 'custom-orders.index', 'fields' => [
            'text_custom_orders_lead' => ['Zdanie pod „Zaprojektujmy to razem”', 3, 400],
        ]],
        ['title' => 'Dla kawiarni i restauracji', 'route' => 'content.b2b', 'fields' => [
            'text_b2b_lead' => ['Zdanie pod nagłówkiem', 3, 400],
            'text_b2b_cta_heading' => ['Pytanie w ramce na dole strony', 2, 120],
            'text_b2b_cta' => ['Zdanie pod pytaniem', 2, 300],
        ]],
        ['title' => 'Wysyłka i pielęgnacja', 'route' => 'content.faq', 'fields' => [
            'text_packing_steps' => ['Jak pakuję i wysyłam — każdy krok w osobnej linii', 5, 800],
            'text_packing_steps_en' => ['To samo po angielsku — pokaże się w angielskiej wersji strony', 5, 800],
        ]],
        ['title' => 'Kontakt', 'route' => 'content.contact', 'fields' => [
            'text_contact_lead' => ['Zdanie pod „Napisz do mnie”', 2, 300],
        ]],
        ['title' => 'Stopka na każdej stronie', 'route' => null, 'fields' => [
            'text_footer_tagline' => ['Hasło pod logo', 2, 200],
        ]],
    ];

    /** @var string */
    protected $errorBag = 'teksty';

    /**
     * @return list<string>
     */
    public static function textKeys(): array
    {
        return collect(self::PAGES)->flatMap(fn (array $page) => array_keys($page['fields']))->all();
    }

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return collect(self::PAGES)
            ->flatMap(fn (array $page) => $page['fields'])
            ->map(fn (array $field) => ['nullable', 'string', 'max:'.$field[2]])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.max' => 'Ten tekst zmieszczę do :max znaków — skróć go o kilka słów',
        ];
    }

    /**
     * @return array<string, ?string>
     */
    public function settings(): array
    {
        return collect(self::textKeys())
            ->mapWithKeys(fn (string $key) => [$key => filled($value = $this->validated($key)) ? str_replace("\r\n", "\n", trim($value)) : null])
            ->all();
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.content.edit').'#teksty';
    }
}
