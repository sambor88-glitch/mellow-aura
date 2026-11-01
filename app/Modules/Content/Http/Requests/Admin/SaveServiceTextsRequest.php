<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Enums\Service;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The sentences on a service page. The panel form and the validation read the same list, so a new text needs one
 * entry here and its place in content::services.show. Enter in the heading moves a word to the next line, as the page
 * shows it. An empty text is saved as null and does not show.
 */
class SaveServiceTextsRequest extends FormRequest
{
    /**
     * The texts by the part of the page they stand in, in the order of the page. A field without `rows` is one line.
     *
     * @var list<array{title: string, fields: array<string, array{label: string, max: int, rows?: int, hint?: string}>}>
     */
    public const PARTS = [
        ['title' => 'Góra strony', 'fields' => [
            'eyebrow' => ['label' => 'Nad nagłówkiem — małymi literami', 'max' => 60],
            'heading' => ['label' => 'Nagłówek', 'max' => 120, 'rows' => 3, 'hint' => 'Enter przenosi wyraz do nowej linii — dokładnie tak, jak zobaczy to klient.'],
            'lead' => ['label' => 'Pierwszy akapit — większym pismem', 'max' => 400, 'rows' => 4],
            'lead_2' => ['label' => 'Drugi akapit', 'max' => 400, 'rows' => 3],
            'cta' => ['label' => 'Napis na przycisku', 'max' => 40, 'hint' => 'Przycisk otwiera formularz zamówienia z tą usługą. Puste pole — przycisk powie „Napisz do mnie”.'],
        ]],
        ['title' => 'Przed i po', 'fields' => [
            'examples_lead' => ['label' => 'Zdanie pod nagłówkiem „Przed i po”', 'max' => 300, 'rows' => 2, 'hint' => 'Po nim strona sama dopisze, jak przesuwać uchwyt na zdjęciu.'],
        ]],
        ['title' => 'Cennik', 'fields' => [
            'prices_heading' => ['label' => 'Nagłówek nad cennikiem', 'max' => 80],
            'note' => ['label' => 'Notka pod cennikiem', 'max' => 400, 'rows' => 3],
        ]],
        ['title' => 'Ramka obok cennika', 'fields' => [
            'card_eyebrow' => ['label' => 'Nad tekstem — małymi literami', 'max' => 40],
            'card_heading' => ['label' => 'Zdanie większym pismem', 'max' => 200, 'rows' => 2],
            'card_list' => ['label' => 'Lista — każda pozycja w osobnej linii', 'max' => 600, 'rows' => 4],
            'card_text' => ['label' => 'Akapit na dole ramki', 'max' => 600, 'rows' => 3],
        ]],
    ];

    /** @var string */
    protected $errorBag = 'teksty';

    /**
     * Every text with its rules, without the parts.
     *
     * @return array<string, array{label: string, max: int, rows?: int, hint?: string}>
     */
    public static function fields(): array
    {
        return collect(self::PARTS)->flatMap(fn (array $part) => $part['fields'])->all();
    }

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
        return collect(self::fields())->map(fn (array $field) => ['nullable', 'string', 'max:'.$field['max']])->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['*.max' => 'Ten tekst zmieszczę do :max znaków — skróć go o kilka słów'];
    }

    /**
     * @return array<string, ?string>
     */
    public function settings(): array
    {
        return collect(self::fields())
            ->mapWithKeys(fn (array $field, string $name) => [$this->service()->setting($name) => $this->clean($this->validated($name), isset($field['rows']))])
            ->all();
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.services.edit', $this->route('service')).'#teksty';
    }

    /** A text field keeps its lines; a one-line field gets one line even if a pasted text brought more. */
    private function clean(?string $value, bool $lines): ?string
    {
        if (blank($value)) {
            return null;
        }

        return $lines ? str_replace("\r\n", "\n", trim($value)) : trim((string) preg_replace('/\s+/u', ' ', $value));
    }
}
