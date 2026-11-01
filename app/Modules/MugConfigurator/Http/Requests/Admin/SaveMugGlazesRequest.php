<?php

namespace App\Modules\MugConfigurator\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * The inside colours a customer picks from (MA-350): a name and a colour each, in the order of the rows.
 * A saved glaze keeps its code, so baskets and the English names still find it after Kasia renames it;
 * a new one gets a code from its name. A row without a name is not saved.
 */
class SaveMugGlazesRequest extends FormRequest
{
    public const MAX_GLAZES = 12;

    /** @var string */
    protected $errorBag = 'szkliwa';

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
            'glazes' => ['required', 'array', 'min:1', 'max:'.self::MAX_GLAZES],
            'glazes.*.code' => ['nullable', 'string', 'regex:/^[a-z0-9_]{1,40}$/'],
            'glazes.*.name' => ['required', 'string', 'max:24', 'distinct:ignore_case'],
            'glazes.*.hex' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'glazes.required' => 'Zostaw choć jeden kolor — bez niego kubka nie da się zamówić',
            'glazes.min' => 'Zostaw choć jeden kolor — bez niego kubka nie da się zamówić',
            'glazes.max' => 'Zmieszczę do :max kolorów',
            'glazes.*.code.*' => 'Coś poszło nie tak z tym kolorem — odśwież stronę i spróbuj jeszcze raz',
            'glazes.*.name.required' => 'Nazwij kolor — np. Turkus',
            'glazes.*.name.max' => 'Nazwę koloru zmieszczę do :max znaków',
            'glazes.*.name.distinct' => 'Dwa kolory mają tę samą nazwę — klient ich nie odróżni',
            'glazes.*.hex.*' => 'Wybierz kolor z palety',
        ];
    }

    /**
     * @return array{mug_glazes: list<array{code: string, name: string, hex: string}>}
     */
    public function settings(): array
    {
        $codes = [];

        return [
            'mug_glazes' => collect((array) $this->validated('glazes'))
                ->map(function (array $row) use (&$codes) {
                    $code = filled($row['code'] ?? null) ? $row['code'] : (Str::slug($row['name'], '_') ?: 'glaze');
                    $base = $code;

                    for ($i = 2; in_array($code, $codes, true); $i++) {
                        $code = $base.'_'.$i;
                    }

                    $codes[] = $code;

                    return ['code' => $code, 'name' => $row['name'], 'hex' => strtoupper($row['hex'])];
                })
                ->values()
                ->all(),
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'glazes' => collect((array) $this->input('glazes', []))
                ->filter(fn (mixed $row) => is_array($row) && ! ($row['remove'] ?? false) && filled($row['name'] ?? null))
                ->map(fn (array $row) => [
                    'code' => $row['code'] ?? null,
                    'name' => is_string($row['name']) ? trim($row['name']) : $row['name'],
                    'hex' => is_string($row['hex'] ?? null) ? trim($row['hex']) : ($row['hex'] ?? null),
                ])
                ->values()
                ->all(),
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.mug.edit').'#szkliwa';
    }
}
