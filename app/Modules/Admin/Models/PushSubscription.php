<?php

namespace App\Modules\Admin\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone or browser that gets the panel's notifications (MA-345). The keys come from the browser and only let
 * the server encrypt a message for that one device; they are not a login.
 *
 * @property int $user_id
 * @property string $endpoint
 * @property string $endpoint_hash
 * @property string $public_key
 * @property string $auth_token
 * @property string $content_encoding
 * @property string $device „iPhone”, „Android · Chrome”
 * @property ?CarbonImmutable $last_sent_at
 * @property CarbonImmutable $created_at
 */
#[Fillable(['user_id', 'endpoint', 'endpoint_hash', 'public_key', 'auth_token', 'content_encoding', 'device', 'last_sent_at'])]
#[Hidden(['endpoint', 'public_key', 'auth_token'])]
class PushSubscription extends Model
{
    public static function hash(string $endpoint): string
    {
        return hash('sha256', $endpoint);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * A short name of the device from its browser's description, enough to tell two phones apart on the list.
     */
    public static function deviceName(?string $userAgent): string
    {
        $agent = (string) $userAgent;

        $system = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Macintosh') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'Urządzenie',
        };

        $browser = match (true) {
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'SamsungBrowser') => 'Samsung Internet',
            str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS') => 'Firefox',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS') => 'Chrome',
            str_contains($agent, 'Safari/') => 'Safari',
            default => null,
        };

        // On an iPhone every browser is Safari underneath, and the panel from the home screen is the only way in.
        return $browser === null || in_array($system, ['iPhone', 'iPad'], true) ? $system : $system.' · '.$browser;
    }

    protected function casts(): array
    {
        return [
            'last_sent_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
        ];
    }
}
