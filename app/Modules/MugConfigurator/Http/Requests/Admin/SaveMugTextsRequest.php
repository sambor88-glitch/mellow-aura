<?php

namespace App\Modules\MugConfigurator\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The sentences on /kubek-z-napisem. An empty one is not shown.
 */
class SaveMugTextsRequest extends FormRequest
{
    /** Setting key => the most characters it takes. */
    public const FIELDS = [
        'text_mug_heading' => 120,
        'text_mug_lead' => 400,
        'text_mug_note' => 300,
        'text_mug_lead_time' => 60,
        'text_mug_bulk_order' => 200,
        'text_mug_refusals' => 200,
    ];

    /** @var string */
    protected $errorBag = 'teksty';

    public function authorize(): bool
    {
        return true;
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
        return ['*.max' => 'Ten tekst zmieszczę do :max znaków'];
    }

    /**
     * @return array<string, ?string>
     */
    public function settings(): array
    {
        return collect(self::FIELDS)
            ->map(fn (int $max, string $key) => filled($value = $this->validated($key)) ? str_replace("\r\n", "\n", trim($value)) : null)
            ->all();
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.mug.edit').'#teksty';
    }
}
