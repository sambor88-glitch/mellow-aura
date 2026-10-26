<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Exceptions\InstagramFeedUnavailable;
use App\Modules\Content\Models\InstagramPost;
use App\Modules\Settings\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Reads the Behold.so JSON feed pasted in the panel and keeps the newest posts, each photo saved on the shop's own
 * disk: the privacy policy promises that looking at them sends nobody's IP address to Instagram or Behold.
 * Only Behold's addresses are fetched, so the field can't make the server call anything else.
 */
class RefreshInstagramFeed
{
    public const POSTS = 6;

    private const FEED_HOST = 'feeds.behold.so';

    private const IMAGE_HOST = 'behold.pictures';

    private const IMAGE_TYPES = ['image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/png' => 'png'];

    private const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    public function __construct(private Settings $settings) {}

    public static function isFeedUrl(string $url): bool
    {
        return str_starts_with($url, 'https://') && parse_url($url, PHP_URL_HOST) === self::FEED_HOST;
    }

    /**
     * @return int how many posts the home page shows now
     *
     * @throws InstagramFeedUnavailable when the feed can't be read; the posts saved before stay
     */
    public function __invoke(): int
    {
        $url = (string) $this->settings->get('instagram_feed_url');

        if ($url === '') {
            $this->keepOnly([]);

            return 0;
        }

        if (! self::isFeedUrl($url)) {
            throw new InstagramFeedUnavailable('To nie jest adres kanału z Behold — powinien zaczynać się od https://feeds.behold.so/');
        }

        $saved = $this->posts($url)->map(fn (array $post) => $this->save($post))->filter()->values();
        $this->keepOnly($saved->all());

        return $saved->count();
    }

    /**
     * The newest posts with a photo, and with the hashtag from the panel when there is one.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function posts(string $url): Collection
    {
        try {
            $response = Http::timeout(10)->acceptJson()->get($url);
        } catch (Throwable $exception) {
            report($exception);

            throw new InstagramFeedUnavailable('Kanał z Behold nie odpowiada — spróbuj za chwilę');
        }

        $feed = $response->successful() ? $response->json() : null;
        // Behold sends an object with „posts”; older feeds were the list of posts itself.
        $posts = is_array($feed) ? ($feed['posts'] ?? (array_is_list($feed) ? $feed : null)) : null;

        if (! is_array($posts)) {
            throw new InstagramFeedUnavailable('Pod tym adresem nie ma kanału z Behold — skopiuj adres JSON jeszcze raz');
        }

        $hashtag = Str::lower(ltrim(trim((string) $this->settings->get('instagram_feed_hashtag')), '#'));

        return collect($posts)
            ->filter(fn (mixed $post) => is_array($post) && filled($post['id'] ?? null) && filled($post['timestamp'] ?? null) && str_starts_with((string) ($post['permalink'] ?? ''), 'https://www.instagram.com/'))
            ->filter(fn (array $post) => $hashtag === '' || collect((array) ($post['hashtags'] ?? []))->map(fn (mixed $tag) => Str::lower(ltrim((string) $tag, '#')))->contains($hashtag))
            ->sortByDesc(fn (array $post) => (string) $post['timestamp'])
            ->take(self::POSTS)
            ->values();
    }

    /**
     * The post with its photo on the shop's disk, or null when the photo can't be fetched.
     *
     * @param  array<string, mixed>  $post
     */
    private function save(array $post): ?InstagramPost
    {
        $existing = InstagramPost::query()->where('external_id', (string) $post['id'])->first();
        $path = $existing?->image_path;

        if ($path === null || ! Storage::disk('public')->exists($path)) {
            $path = $this->download((string) $post['id'], $post['sizes']['medium']['mediaUrl'] ?? $post['sizes']['large']['mediaUrl'] ?? null);
        }

        if ($path === null) {
            return $existing;
        }

        return InstagramPost::query()->updateOrCreate(['external_id' => (string) $post['id']], [
            'permalink' => (string) $post['permalink'],
            'caption' => filled($post['caption'] ?? null) ? (string) $post['caption'] : null,
            'alt_text' => filled($post['altText'] ?? null) ? Str::limit((string) $post['altText'], 250) : null,
            'image_path' => $path,
            'posted_at' => CarbonImmutable::parse((string) $post['timestamp']),
        ]);
    }

    private function download(string $id, mixed $url): ?string
    {
        $host = is_string($url) ? (string) parse_url($url, PHP_URL_HOST) : '';

        if (! str_starts_with((string) $url, 'https://') || ($host !== self::IMAGE_HOST && ! str_ends_with($host, '.'.self::IMAGE_HOST))) {
            return null;
        }

        try {
            $response = Http::timeout(15)->get($url);
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }

        $extension = self::IMAGE_TYPES[strtolower(trim(explode(';', (string) $response->header('Content-Type'))[0]))] ?? null;

        if (! $response->successful() || $extension === null || strlen($response->body()) > self::MAX_IMAGE_BYTES) {
            return null;
        }

        $path = 'instagram/'.Str::slug($id).'.'.$extension;
        Storage::disk('public')->put($path, $response->body());

        return $path;
    }

    /**
     * Removes the posts that dropped out of the feed, and their photos.
     *
     * @param  list<InstagramPost>  $posts
     */
    private function keepOnly(array $posts): void
    {
        DB::transaction(function () use ($posts) {
            $gone = InstagramPost::query()->whereKeyNot(array_map(fn (InstagramPost $post) => $post->id, $posts))->get();

            Storage::disk('public')->delete($gone->pluck('image_path')->all());
            InstagramPost::query()->whereKey($gone->modelKeys())->delete();
        });
    }
}
