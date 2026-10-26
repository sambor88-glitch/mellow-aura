<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Enums\Service;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The sentences on a service page. Enter in the heading moves a word to the next line, as the page shows it.
 */
class SaveServiceTextsRequest extends FormRequest
{
    /** @var array<string, int> field => max length */
    public const FIELDS = ['heading' => 120, 'lead' => 400, 'lead_2' => 400, 'note' => 400];

    /** @var string */
    protected $errorBag = 'teksty';

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
        return collect(self::FIELDS)->map(fn (int $max) => ['nullable', 'string', 'max:'.$max])->all();
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['*.max' => 'Ten tekst zmieszczę do :max znaków — skróć go o kilka słów'];
    }

    /**
     * @return array<string, ?string>
     */
    public function settings(): array
    {
        return collect(array_keys(self::FIELDS))
            ->mapWithKeys(fn (string $field) => [
                $this->service()->setting($field) => filled($value = $this->validated($field)) ? str_replace("\r\n", "\n", trim($value)) : null,
            ])
            ->all();
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.services.edit', $this->route('service')).'#teksty';
    }
}
