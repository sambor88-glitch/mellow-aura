<?php

namespace App\Modules\Content\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * A post from Kasia's Instagram with its photo saved on the shop's disk, so a visitor's browser never asks
 * Instagram or the feed provider for it.
 */
#[Fillable(['external_id', 'permalink', 'caption', 'alt_text', 'image_path', 'focus_x', 'focus_y', 'posted_at'])]
class InstagramPost extends Model
{
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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'focus_x' => 'integer',
            'focus_y' => 'integer',
            'posted_at' => 'datetime',
        ];
    }
}
