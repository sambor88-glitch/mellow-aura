<?php

namespace App\Modules\Settings;

use App\Modules\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Read access to panel settings for every module.
 *
 * All values are loaded at once and kept in the file cache; UpdateSetting
 * clears it. Empty values come back as the default, so the page can skip them.
 */
class Settings
{
    public const CACHE_KEY = 'settings.all';

    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->all()[$key] ?? null;

        return $value === null || $value === '' || $value === [] ? $default : $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->values ??= Cache::rememberForever(
            self::CACHE_KEY,
            fn () => Setting::query()->pluck('value', 'key')->all(),
        );
    }

    public function flush(): void
    {
        $this->values = null;
        Cache::forget(self::CACHE_KEY);
    }
}
