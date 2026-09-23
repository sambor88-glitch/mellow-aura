<?php

namespace App\Modules\MugConfigurator\Http\Requests\Admin;

use App\Modules\MugConfigurator\Support\MugOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Where the text sits on the photo and its colour — Kasia sets them once for her photo; customers do not choose.
 */
class SaveMugLookRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'napis';

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
            'x_percent' => ['required', 'integer', 'min:10', 'max:90'],
            'y_percent' => ['required', 'integer', 'min:10', 'max:90'],
            'size_percent' => ['required', 'integer', 'min:50', 'max:220'],
            'rotation_deg' => ['required', 'integer', 'min:-15', 'max:15'],
            'ink' => ['required', Rule::in(app(MugOptions::class)->inks()->pluck('code')->all())],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            '*.required' => 'Ustaw suwak jeszcze raz',
            '*.integer' => 'Ustaw suwak jeszcze raz',
            '*.min' => 'Ustaw suwak jeszcze raz',
            '*.max' => 'Ustaw suwak jeszcze raz',
            'ink.in' => 'Wybierz kolor napisu z palety',
        ];
    }

    /**
     * @return array{mug_text_position: array{x_percent: int, y_percent: int, size_percent: int, rotation_deg: int}, mug_ink_color: string}
     */
    public function settings(): array
    {
        return [
            'mug_text_position' => [
                'x_percent' => (int) $this->validated('x_percent'),
                'y_percent' => (int) $this->validated('y_percent'),
                'size_percent' => (int) $this->validated('size_percent'),
                'rotation_deg' => (int) $this->validated('rotation_deg'),
            ],
            'mug_ink_color' => (string) $this->validated('ink'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.mug.edit').'#napis';
    }
}
