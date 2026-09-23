<?php

namespace App\Modules\Content\Http\Requests\Admin;

use App\Modules\Content\Actions\RefreshInstagramFeed;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The Instagram card: the Behold.so JSON feed address and an optional hashtag the posts must have.
 */
class SaveInstagramFeedRequest extends FormRequest
{
    /** @var string */
    protected $errorBag = 'instagram';

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
            'instagram_feed_url' => ['nullable', 'string', 'max:255', function (string $attribute, mixed $value, Closure $fail): void {
                if (! RefreshInstagramFeed::isFeedUrl((string) $value)) {
                    $fail('Wklej adres kanału JSON z Behold — zaczyna się od https://feeds.behold.so/');
                }
            }],
            'instagram_feed_hashtag' => ['nullable', 'string', 'max:60', 'regex:/^#?[\p{L}\p{N}_]+$/u'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'instagram_feed_url.max' => 'Ten adres jest za długi — skopiuj go z Behold jeszcze raz',
            'instagram_feed_hashtag.max' => 'Hashtag zmieszczę do :max znaków',
            'instagram_feed_hashtag.regex' => 'Wpisz jeden hashtag bez spacji, np. #zpracowni',
        ];
    }

    /**
     * @return array{instagram_feed_url: ?string, instagram_feed_hashtag: ?string}
     */
    public function settings(): array
    {
        $hashtag = ltrim((string) $this->validated('instagram_feed_hashtag'), '#');

        return [
            'instagram_feed_url' => $this->validated('instagram_feed_url'),
            'instagram_feed_hashtag' => $hashtag === '' ? null : '#'.$hashtag,
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'instagram_feed_url' => filled($url = $this->input('instagram_feed_url')) ? trim((string) $url) : null,
            'instagram_feed_hashtag' => filled($tag = $this->input('instagram_feed_hashtag')) ? trim((string) $tag) : null,
        ]);
    }

    protected function getRedirectUrl(): string
    {
        return route('admin.content.edit').'#instagram';
    }
}
