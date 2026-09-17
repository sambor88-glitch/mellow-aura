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
#[Fillable(['external_id', 'permalink', 'caption', 'alt_text', 'image_path', 'posted_at'])]
class InstagramPost extends Model
{
    public function imageUrl(): string
    {
        return Storage::disk('public')->url($this->image_path);
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
            'posted_at' => 'datetime',
        ];
    }
}
