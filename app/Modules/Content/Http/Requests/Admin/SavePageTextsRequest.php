<?php

namespace App\Modules\Content\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The sentences on the content pages. The panel form and the validation read the same list,
 * so a new text needs one entry here. An empty text is saved as null and does not show.
 */
class SavePageTextsRequest extends FormRequest
{
    /** @var list<array{title: string, route: ?string, fields: array<string, array{0: string, 1: int, 2: int, 3?: string}>}> label, rows, max length, hint */
    public const PAGES = [
        ['title' => 'Strona główna', 'route' => 'home', 'fields' => [
            'text_home_lead_mug' => ['Zdanie o kubkach pod „Glina i jedwab z jednej pary rąk”', 2, 200, 'Dopisuje się po zdaniu o ceramice i jedwabiu.'],
            'text_home_fact_handmade' => ['Liczba przy „ręczna robota” pod zdjęciem', 1, 12, 'Np. 100%. Puste pole usuwa ten kafelek.'],
            'text_home_materials' => ['Słowa w przesuwanym pasku — każde w osobnej linii', 4, 200, 'Pierwszy rząd pod zdjęciem: materiały, z których robisz.'],
            'text_home_kasia_heading' => ['„Cześć, jestem Kasia” — nagłówek', 2, 160],
            'text_home_kasia_paragraph' => ['„Cześć, jestem Kasia” — akapit', 4, 600],
            'text_home_card_b2b' => ['Kafelek „Ceramika dla kawiarni i restauracji” — zdanie pod nagłówkiem', 2, 200],
        ]],
        ['title' => 'Sklep', 'route' => 'shop.index', 'fields' => [
            'text_shop_intro_note' => ['Zdanie po „Wszystko, co stoi teraz na półce w pracowni.”', 2, 200],
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
            'text_reply_time' => ['Jak szybko odpisujesz', 1, 120, 'Np. „Odpisuję zwykle tego samego dnia.” Widać je po wysłaniu wiadomości, w opisie strony dla Google i na dole „Wysyłka i pielęgnacja”.'],
        ]],
        ['title' => 'Płatność i kasa', 'route' => null, 'fields' => [
            'text_blik_time' => ['Ile trwa płatność BLIK-iem', 1, 30, 'Np. 10 sekund. Widać to na stronie głównej, na karcie produktu i przy kubku z napisem. Puste pole zostawia samo „zapłacisz kodem z aplikacji banku”.'],
            'text_checkout_packing' => ['Jak pakujesz — zdanie pod przyciskiem płatności w kasie', 2, 160],
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
