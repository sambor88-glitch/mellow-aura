<?php

namespace App\Modules\Workshops\Http\Requests\Admin;

use App\Modules\Shared\Http\Requests\Concerns\EditsListRows;
use App\Modules\Shared\Rules\PriceBeforeReduction;
use App\Modules\Shared\Support\Money;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * The workshop price list, the sentence above it and what to bring, which goes only into the booking mails. A row keeps its code and unit for bookings after
 * the holidays; a new row gets a code from its name.
 */
class SaveWorkshopTypesRequest extends FormRequest
{
    use EditsListRows;

    public const FIELDS = ['name', 'price', 'compare_at', 'unit_label', 'duration_label', 'group_label', 'summary', 'includes', 'code', 'unit'];

    private const PRICE = '/^\d{1,5}([.,]\d{1,2})?$/';

    /** @var string */
    protected $errorBag = 'warsztaty';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $row = fn (array $rules) => ['exclude_if:workshops.*.remove,1', ...$rules];

        return [
            'text_workshops_lead' => ['nullable', 'string', 'max:300'],
            'workshop_what_to_bring' => ['nullable', 'string', 'max:400'],
            'workshops' => ['nullable', 'array', 'max:12'],
            'workshops.*.name' => $row(['nullable', 'string', 'max:60', 'required_with:workshops.*.price']),
            'workshops.*.price' => $row(['nullable', 'regex:'.self::PRICE, 'required_with:workshops.*.name']),
            'workshops.*.compare_at' => $row(['nullable', new PriceBeforeReduction]),
            'workshops.*.unit_label' => $row(['nullable', 'string', 'max:30']),
            'workshops.*.duration_label' => $row(['nullable', 'string', 'max:30']),
            'workshops.*.group_label' => $row(['nullable', 'string', 'max:40']),
            'workshops.*.summary' => $row(['nullable', 'string', 'max:220']),
            'workshops.*.includes' => $row(['nullable', 'string', 'max:400']),
            'workshops.*.code' => ['nullable', 'string', 'max:60'],
            'workshops.*.unit' => ['nullable', 'string', 'max:30'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'text_workshops_lead.max' => 'Zdanie zmieszczę do :max znaków',
            'workshop_what_to_bring.max' => 'Listę zmieszczę do :max znaków',
            'workshops.max' => 'Zmieszczę do :max warsztatów',
            'workshops.*.name.required_with' => 'Wpisz nazwę warsztatu do tej ceny',
            'workshops.*.name.max' => 'Nazwę zmieszczę do :max znaków',
            'workshops.*.price.required_with' => 'Wpisz cenę, np. 220',
            'workshops.*.price.regex' => 'Wpisz cenę, np. 220 albo 220,50',
            'workshops.*.unit_label.max' => 'To pole zmieszczę do :max znaków',
            'workshops.*.duration_label.max' => 'To pole zmieszczę do :max znaków',
            'workshops.*.group_label.max' => 'To pole zmieszczę do :max znaków',
            'workshops.*.summary.max' => 'Opis zmieszczę do :max znaków',
            'workshops.*.includes.max' => 'Listę zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array{text_workshops_lead: ?string, workshop_what_to_bring: list<string>, workshop_types: list<array<string, mixed>>}
     */
    public function settings(): array
    {
        return [
            'text_workshops_lead' => filled($lead = $this->validated('text_workshops_lead')) ? trim($lead) : null,
            'workshop_what_to_bring' => $this->lines($this->validated('workshop_what_to_bring')),
            'workshop_types' => array_map(fn (array $row) => [
                'code' => $row['code'] ?? Str::slug((string) $row['name'], '_'),
                'name' => $row['name'],
                'duration_label' => $row['duration_label'],
                'group_label' => $row['group_label'],
                'price_gross' => Money::parse((string) $row['price']),
                'compare_at_price' => $row['compare_at'] === null ? null : Money::parse($row['compare_at']),
                'unit' => $row['unit'],
                'unit_label' => $row['unit_label'],
                'summary' => $row['summary'],
                'includes' => $this->lines($row['includes']),
            ], $this->listRows('workshops', self::FIELDS)),
        ];
    }

    /**
     * One item per line, without the empty ones.
     *
     * @return list<string>
     */
    private function lines(?string $text): array
    {
        return array_values(array_filter(array_map('trim', explode("\n", (string) $text)), fn (string $line) => $line !== ''));
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.workshops.edit').'#cennik';
    }
}
