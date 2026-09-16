<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Shared\Http\Requests\Concerns\EditsListRows;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The cards on /ceramika-dla-gastronomii: a short promise to a café or a restaurant and a sentence under it.
 * Their titles also make up the page's description for Google.
 */
class SaveB2bFactsRequest extends FormRequest
{
    use EditsListRows;

    /** @var string */
    protected $errorBag = 'gastronomia';

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
            'b2b' => ['nullable', 'array', 'max:8'],
            'b2b.*.title' => ['exclude_if:b2b.*.remove,1', 'nullable', 'string', 'max:40', 'required_with:b2b.*.text'],
            'b2b.*.text' => ['exclude_if:b2b.*.remove,1', 'nullable', 'string', 'max:200'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'b2b.max' => 'Zmieszczę do :max kart',
            'b2b.*.title.required_with' => 'Wpisz nagłówek karty, np. Próbka przed serią',
            'b2b.*.title.max' => 'Nagłówek karty zmieszczę do :max znaków',
            'b2b.*.text.max' => 'Zdanie pod nagłówkiem zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array{b2b_facts: list<array<string, ?string>>}
     */
    public function settings(): array
    {
        return ['b2b_facts' => $this->listRows('b2b', ['title', 'text'])];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.content.edit').'#gastronomia';
    }
}
