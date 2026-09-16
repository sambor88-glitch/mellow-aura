<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Http\Requests\Admin\Concerns\EditsListRows;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The „dobrze wiedzieć” card on /pracownia: a short fact and one sentence under it.
 */
class SaveStudioFactsRequest extends FormRequest
{
    use EditsListRows;

    /** @var string */
    protected $errorBag = 'pracownia';

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
            'facts' => ['nullable', 'array', 'max:12'],
            'facts.*.title' => ['exclude_if:facts.*.remove,1', 'nullable', 'string', 'max:40', 'required_with:facts.*.text'],
            'facts.*.text' => ['exclude_if:facts.*.remove,1', 'nullable', 'string', 'max:160'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'facts.max' => 'Zmieszczę do :max faktów — karta ma zostać krótka',
            'facts.*.title.required_with' => 'Wpisz fakt, np. Do 6 osób',
            'facts.*.title.max' => 'Fakt zmieszczę do :max znaków',
            'facts.*.text.max' => 'Zdanie pod faktem zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array{studio_facts: list<array<string, ?string>>}
     */
    public function settings(): array
    {
        return ['studio_facts' => $this->listRows('facts', ['title', 'text'])];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.content.edit').'#pracownia';
    }
}
