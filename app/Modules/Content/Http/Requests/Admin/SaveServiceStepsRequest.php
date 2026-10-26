<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Enums\Service;
use App\Modules\Shared\Http\Requests\Concerns\EditsListRows;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The numbered steps on a service page: how the fabric or the plant gets to Kasia and back.
 */
class SaveServiceStepsRequest extends FormRequest
{
    use EditsListRows;

    /** @var string */
    protected $errorBag = 'kroki';

    public function authorize(): bool
    {
        return true;
    }

    public function service(): Service
    {
        return Service::fromSlug((string) $this->route('service')) ?? abort(404);
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
            'steps.*.title.required_with' => 'Nazwij ten krok, np. Przysyłasz tkaninę',
            'steps.*.title.max' => 'Nazwę kroku zmieszczę do :max znaków',
            'steps.*.text.max' => 'Opis kroku zmieszczę do :max znaków',
        ];
    }

    /**
     * @return array<string, list<array<string, ?string>>>
     */
    public function settings(): array
    {
        return [$this->service()->setting('steps') => $this->listRows('steps', ['title', 'text'])];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.services.edit', $this->route('service')).'#kroki';
    }
}
