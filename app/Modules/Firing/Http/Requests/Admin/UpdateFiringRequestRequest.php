<?php

namespace App\Modules\Firing\Http\Requests\Admin;

use App\Modules\Firing\Enums\FiringRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One batch in the panel: its status and the day it comes in. Any day will do — Kasia may take a batch
 * outside her usual load day.
 */
class UpdateFiringRequestRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'zgloszenie';

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
            'status' => ['required', Rule::enum(FiringRequestStatus::class)],
            'drop_off_on' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.*' => 'Wybierz status z listy',
            'drop_off_on.*' => 'Wpisz datę, np. 2026-10-29',
        ];
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.firing.requests.index').'#zgloszenie-'.$this->route('firingRequest')->id;
    }
}
