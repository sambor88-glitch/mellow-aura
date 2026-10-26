<?php

namespace App\Modules\Gifts\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * „Szukam prezentu” in the panel: the budget ranges in whole złoty and the two sentences on the page.
 * A row left empty is not saved; an empty "to" makes an open range, like „powyżej 400 zł”.
 */
class SaveGiftFinderRequest extends FormRequest
{
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
        return [
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
     * @return array{gift_budget_ranges: list<array{min_gross: int, max_gross: ?int}>, text_gifts_lead: ?string, text_gifts_voucher_note: ?string}
     */
    public function settings(): array
    {
        return [
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
