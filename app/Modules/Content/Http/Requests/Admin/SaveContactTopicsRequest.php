<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Http\Requests\Admin\Concerns\EditsListRows;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The choices under „W jakiej sprawie?” in the contact form. With none, the field is left out.
 */
class SaveContactTopicsRequest extends FormRequest
{
    use EditsListRows;

    /** @var string */
    protected $errorBag = 'kontakt';

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
            'topics' => ['nullable', 'array', 'max:12'],
            'topics.*.label' => ['exclude_if:topics.*.remove,1', 'nullable', 'string', 'max:60', 'distinct:ignore_case'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'topics.max' => 'Zmieszczę do :max spraw — dłuższa lista utrudnia wybór',
            'topics.*.label.max' => 'Sprawę zmieszczę do :max znaków',
            'topics.*.label.distinct' => 'Ta sprawa jest już na liście',
        ];
    }

    /**
     * @return array{contact_form_topics: list<string>}
     */
    public function settings(): array
    {
        return ['contact_form_topics' => array_column($this->listRows('topics', ['label']), 'label')];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.content.edit').'#kontakt';
    }
}
