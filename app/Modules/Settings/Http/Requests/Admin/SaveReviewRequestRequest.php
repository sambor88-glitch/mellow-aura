<?php

namespace App\Modules\Settings\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The mail asking for a Google review after the delivery (MA-329): the link to the review form and how many days
 * after the order is completed it goes out. Either field left empty stops the mail.
 */
class SaveReviewRequestRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'opinie';

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
            'review_request_url' => ['nullable', 'url:https', 'max:255'],
            'review_request_days' => ['nullable', 'integer', 'between:1,60'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'review_request_url.url' => 'Wklej cały link, zaczynający się od https://',
            'review_request_url.max' => 'Link zmieszczę do :max znaków',
            'review_request_days.integer' => 'Wpisz liczbę dni, np. 7',
            'review_request_days.between' => 'Wpisz od :min do :max dni',
        ];
    }

    /**
     * @return array{review_request_url: ?string, review_request_days: ?int}
     */
    public function settings(): array
    {
        $days = $this->validated('review_request_days');

        return [
            'review_request_url' => $this->validated('review_request_url'),
            'review_request_days' => $days === null ? null : (int) $days,
        ];
    }

    protected function prepareForValidation(): void
    {
        $url = trim((string) $this->input('review_request_url'));
        $days = trim((string) $this->input('review_request_days'));

        $this->merge([
            'review_request_url' => $url === '' ? null : (preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) ? $url : 'https://'.$url),
            'review_request_days' => $days === '' ? null : $days,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.settings.edit').'#opinie';
    }
}
