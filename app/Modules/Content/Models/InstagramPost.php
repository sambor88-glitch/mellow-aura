<?php

namespace App\Modules\Content\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A post from Kasia's Instagram with its photo saved on the shop's disk, so a visitor's browser never asks
 * Instagram or the feed provider for it. It comes from the Behold feed or, `is_manual`, from Kasia in the panel.
 */
#[Fillable(['external_id', 'is_manual', 'sort_order', 'permalink', 'caption', 'alt_text', 'image_path', 'focus_x', 'focus_y', 'posted_at'])]
class InstagramPost extends Model
{
    /**
     * What the home page shows: Kasia's own posts first in her order, then the newest from the feed while one is set.
     *
     * @return Collection<int, InstagramPost>
     */
    public static function forHomePage(bool $withFeed, int $limit): Collection
    {
        $manual = self::query()->manual()->ordered()->take($limit)->get();
        $feed = $withFeed && $manual->count() < $limit
            ? self::query()->where('is_manual', false)->latest('posted_at')->take($limit - $manual->count())->get()
            : collect();

        return $manual->concat($feed)->values();
    }

    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
    }

    /**
     * Where the square tile cuts the photo: a portrait post loses its top and bottom, so Kasia moves the point
     * that has to stay in view. A new post keeps the middle.
     */
    public function objectPosition(): string
    {
        return ($this->focus_x ?? 50).'% '.($this->focus_y ?? 50).'%';
    }

    /**
     * Instagram's own description of the photo, else the start of the caption without hashtags.
     */
    public function alt(): string
    {
        $caption = trim((string) preg_replace(['/#[\p{L}\p{N}_]+/u', '/\s+/u'], ['', ' '], (string) $this->caption));

        return $this->alt_text ?: (Str::limit($caption, 120) ?: 'Post z Instagrama MellowAura');
    }

    /**
     * @param  Builder<InstagramPost>  $query
     */
    #[Scope]
    protected function manual(Builder $query): void
    {
        $query->where('is_manual', true);
    }

    /**
     * @param  Builder<InstagramPost>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_manual' => 'boolean',
            'sort_order' => 'integer',
            'focus_x' => 'integer',
            'focus_y' => 'integer',
            'posted_at' => 'datetime',
        ];
    }
}
