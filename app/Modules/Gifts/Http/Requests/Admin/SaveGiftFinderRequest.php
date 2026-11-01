<?php

namespace App\Modules\Gifts\Http\Requests\Admin;

use App\Modules\Catalog\Support\GiftTags;
use App\Modules\Shared\Http\Requests\Concerns\EditsListRows;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * „Szukam prezentu” in the panel, in the order of the page: the heading, the sentence under it, „Dla kogo” and the
 * occasions (MA-352), the budget ranges in whole złoty and the note about vouchers.
 * A tag keeps its key when renamed, so the products ticked with it stay ticked; a new one gets a key from its name.
 * A budget row left empty is not saved; an empty "to" makes an open range, like „powyżej 400 zł”.
 */
class SaveGiftFinderRequest extends FormRequest
{
    use EditsListRows;

    /** The two lists of tags: the field of the form => the setting. */
    public const TAG_LISTS = ['recipients' => GiftTags::RECIPIENTS, 'occasions' => GiftTags::OCCASIONS];

    /** @var string */
    protected $errorBag = 'szukam-prezentu';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tag = fn (string $list) => ['exclude_if:'.$list.'.*.remove,1', 'nullable', 'string', 'max:'.GiftTags::MAX_LABEL];

        return [
            'text_gifts_heading' => ['nullable', 'string', 'max:80'],
            'recipients' => ['nullable', 'array'],
            'recipients.*.label' => $tag('recipients'),
            'recipients.*.key' => ['nullable', 'string', 'max:60'],
            'occasions' => ['nullable', 'array'],
            'occasions.*.label' => $tag('occasions'),
            'occasions.*.key' => ['nullable', 'string', 'max:60'],
            'budgets' => ['array', 'max:8'],
            'budgets.*.min' => ['required', 'integer', 'min:0', 'max:99999'],
            'budgets.*.max' => ['nullable', 'integer', 'gt:budgets.*.min', 'max:99999'],
            'text_gifts_lead' => ['nullable', 'string', 'max:400'],
            'text_gifts_voucher_note' => ['nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text_gifts_heading.max' => 'Nagłówek zmieszczę do :max znaków',
            'recipients.*.label.max' => 'Nazwę zmieszczę do :max znaków',
            'occasions.*.label.max' => 'Nazwę zmieszczę do :max znaków',
            'budgets.max' => 'Zmieszczę do :max przedziałów',
            'budgets.*.min.required' => 'Wpisz kwotę „od” — dla pierwszego przedziału 0',
            'budgets.*.min.*' => 'Wpisz kwotę w pełnych złotych, np. 100',
            'budgets.*.max.gt' => 'Kwota „do” musi być większa niż „od”',
            'budgets.*.max.*' => 'Wpisz kwotę w pełnych złotych, np. 200 — albo zostaw puste pole',
            'text_gifts_lead.max' => 'Ten tekst zmieszczę do :max znaków',
            'text_gifts_voucher_note.max' => 'Ten tekst zmieszczę do :max znaków',
        ];
    }

    /**
     * A list too long for the page, or two tags with one name, which the page could not tell apart.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            foreach (array_keys(self::TAG_LISTS) as $list) {
                if ($validator->errors()->has($list.'.*')) {
                    continue;
                }

                $labels = array_column($this->listRows($list, ['label']), 'label');
                $twice = $labels[collect($labels)->duplicates(fn (string $label) => mb_strtolower($label))->keys()->first()] ?? null;

                if (count($labels) > GiftTags::MAX_TAGS) {
                    $validator->errors()->add($list, 'Zmieszczę do '.GiftTags::MAX_TAGS.' pozycji');
                } elseif ($twice !== null) {
                    $validator->errors()->add($list, '„'.$twice.'” jest na liście dwa razy — zmień nazwę albo usuń jedną');
                }
            }
        }];
    }

    /**
     * @return array{text_gifts_heading: ?string, gift_recipients?: list<array{key: string, label: string}>, gift_occasions?: list<array{key: string, label: string}>, gift_budget_ranges: list<array{min_gross: int, max_gross: ?int}>, text_gifts_lead: ?string, text_gifts_voucher_note: ?string}
     */
    public function settings(): array
    {
        return [
            'text_gifts_heading' => filled($heading = $this->validated('text_gifts_heading')) ? str_replace("\r\n", "\n", trim($heading)) : null,
            // A list the form did not send stays as it is; the card always sends both, with an empty row at least.
            ...collect(self::TAG_LISTS)
                ->filter(fn (string $setting, string $list) => $this->has($list))
                ->mapWithKeys(fn (string $setting, string $list) => [$setting => $this->tags($list)])
                ->all(),
            'gift_budget_ranges' => collect((array) $this->validated('budgets', []))
                ->map(fn (array $row) => [
                    'min_gross' => (int) $row['min'] * 100,
                    'max_gross' => filled($row['max'] ?? null) ? (int) $row['max'] * 100 : null,
                ])
                ->sortBy('min_gross')
                ->values()
                ->all(),
            'text_gifts_lead' => filled($lead = $this->validated('text_gifts_lead')) ? trim($lead) : null,
            'text_gifts_voucher_note' => filled($note = $this->validated('text_gifts_voucher_note')) ? trim($note) : null,
        ];
    }

    /**
     * The rows of one list as {key, label}: a saved row keeps its key, a new one gets one from its name.
     *
     * @return list<array{key: string, label: string}>
     */
    private function tags(string $list): array
    {
        $rows = $this->listRows($list, ['label', 'key']);
        $kept = [];

        foreach ($rows as $index => $row) {
            $key = (string) $row['key'];

            if (preg_match('/^[a-z][a-z0-9_]{0,59}$/', $key) && ! in_array($key, $kept, true)) {
                $kept[$index] = $key;
            }
        }

        $taken = array_values($kept);

        return array_map(function (int $index) use ($rows, $kept, &$taken) {
            $key = $kept[$index] ?? ($taken[] = GiftTags::keyFor((string) $rows[$index]['label'], $taken));

            return ['key' => $key, 'label' => (string) $rows[$index]['label']];
        }, array_keys($rows));
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'budgets' => collect((array) $this->input('budgets', []))
                ->filter(fn (mixed $row) => is_array($row) && ! ($row['remove'] ?? false) && (filled($row['min'] ?? null) || filled($row['max'] ?? null)))
                ->map(fn (array $row) => [
                    'min' => is_string($row['min'] ?? null) ? trim($row['min'], " \u{00A0}zł") : ($row['min'] ?? null),
                    'max' => is_string($row['max'] ?? null) ? trim($row['max'], " \u{00A0}zł") : ($row['max'] ?? null),
                ])
                ->values()
                ->all(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.gifts.edit').'#szukam-prezentu';
    }
}
