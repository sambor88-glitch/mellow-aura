<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Http\Requests\Admin\Concerns\EditsListRows;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Questions and answers on /wysylka-i-pielegnacja, in the order Kasia sets. A question without
 * an answer is saved but does not show on the site.
 */
class SaveFaqRequest extends FormRequest
{
    use EditsListRows;

    /** @var string */
    protected $errorBag = 'czeste-pytania';

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
            'faq' => ['nullable', 'array', 'max:40'],
            'faq.*.question' => ['exclude_if:faq.*.remove,1', 'nullable', 'string', 'max:160', 'required_with:faq.*.answer'],
            'faq.*.answer' => ['exclude_if:faq.*.remove,1', 'nullable', 'string', 'max:1500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'faq.max' => 'Zmieszczę do :max pytań',
            'faq.*.question.required_with' => 'Wpisz pytanie do tej odpowiedzi',
            'faq.*.question.max' => 'Pytanie zmieszczę do :max znaków',
            'faq.*.answer.max' => 'Odpowiedź zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array{faq_items: list<array<string, ?string>>}
     */
    public function settings(): array
    {
        return ['faq_items' => $this->listRows('faq', ['question', 'answer'])];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.content.edit').'#czeste-pytania';
    }
}
