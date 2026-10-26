<?php

namespace App\Modules\Content\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Modules\Content\Actions\RefreshInstagramFeed;
use App\Modules\Content\Exceptions\InstagramFeedUnavailable;
use App\Modules\Content\Http\Requests\Admin\SaveInstagramFeedRequest;
use App\Modules\Settings\Actions\SaveSettings;
use Illuminate\Http\RedirectResponse;

/**
 * Saves the Instagram feed and fetches the photos right away, so Kasia sees at once whether the address works.
 */
class InstagramFeedController extends Controller
{
    public function __invoke(SaveInstagramFeedRequest $request, SaveSettings $saveSettings, RefreshInstagramFeed $refresh): RedirectResponse
    {
        $saveSettings($settings = $request->settings());

        try {
            $count = $refresh();
            $status = match (true) {
                $settings['instagram_feed_url'] === null => 'Sekcja z Instagrama zdjęta ze strony głównej',
                $count === 0 => 'Zapisane, ale w kanale nie ma zdjęć'.($settings['instagram_feed_hashtag'] ? ' z hashtagiem '.$settings['instagram_feed_hashtag'] : '').'. Sekcja pojawi się, gdy będą.',
                default => 'Pobrane zdjęcia: '.$count.'. Są już na stronie głównej.',
            };
        } catch (InstagramFeedUnavailable $exception) {
            $status = 'Zapisane, ale zdjęć nie udało się pobrać: '.$exception->getMessage().'.';
        }

        return to_route('admin.content.edit')->withFragment('instagram')->with('panel_status', $status);
    }
}
