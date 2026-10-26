<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Shared\Http\Requests\Concerns\EditsListRows;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The numbered steps on /zamowienia-indywidualne: from the first message to the parcel.
 */
class SaveCustomOrderStepsRequest extends FormRequest
{
    use EditsListRows;

    /** @var string */
    protected $errorBag = 'zamowienia-indywidualne';

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
            'steps' => ['nullable', 'array', 'max:8'],
            'steps.*.title' => ['exclude_if:steps.*.remove,1', 'nullable', 'string', 'max:60', 'required_with:steps.*.text'],
            'steps.*.text' => ['exclude_if:steps.*.remove,1', 'nullable', 'string', 'max:240'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'steps.max' => 'Zmieszczę do :max kroków',
            'steps.*.title.required_with' => 'Nazwij ten krok, np. Dostajesz szkic i cenę',
            'steps.*.title.max' => 'Nazwę kroku zmieszczę do :max znaków',
            'steps.*.text.max' => 'Opis kroku zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array{custom_order_steps: list<array<string, ?string>>}
     */
    public function settings(): array
    {
        return ['custom_order_steps' => $this->listRows('steps', ['title', 'text'])];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.content.edit').'#zamowienia-indywidualne';
    }
}
