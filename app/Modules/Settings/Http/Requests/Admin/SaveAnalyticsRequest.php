<?php

namespace App\Modules\Settings\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The Google Analytics measurement ID. With it the site asks visitors for consent and loads Analytics
 * after a yes; an empty field turns both the banner and Analytics off.
 */
class SaveAnalyticsRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'statystyki';

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
            'google_analytics_id' => ['nullable', 'regex:/^G-[A-Z0-9]{4,20}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'google_analytics_id.regex' => 'Identyfikator zaczyna się od G-, np. G-AB12CD34EF — skopiuj go z Google Analytics',
        ];
    }

    /**
     * @return array{google_analytics_id: ?string}
     */
    public function settings(): array
    {
        return ['google_analytics_id' => $this->validated('google_analytics_id')];
    }

    protected function prepareForValidation(): void
    {
        $id = preg_replace('/\s+/', '', (string) $this->input('google_analytics_id'));

        $this->merge(['google_analytics_id' => $id === '' ? null : mb_strtoupper($id)]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.settings.edit').'#statystyki';
    }
}
