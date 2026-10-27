<?php

namespace App\Modules\Content\Actions;

use App\Modules\Content\Models\InstagramPost;

/**
 * Saves the point of each Instagram photo that stays in its square tile on the home page.
 * A post that dropped out of the feed in the meantime is skipped.
 */
class SetInstagramFocus
{
    /**
     * @param  array<int, array{x: int, y: int}>  $points  keyed by the post id
     */
    public function __invoke(array $points): void
    {
        InstagramPost::query()->whereKey(array_keys($points))->get()
            ->each(fn (InstagramPost $post) => $post->update([
                'focus_x' => $points[$post->id]['x'],
                'focus_y' => $points[$post->id]['y'],
            ]));
    }
}
