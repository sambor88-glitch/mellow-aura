<?php

namespace App\Modules\Settings;

use App\Modules\Settings\Models\Setting;

/**
 * Reads the owner's settings once per request. An empty value counts as missing,
 * so the site can skip it.
 */
class Settings
{
    /** @var array<string, mixed>|null */
    private ?array $values = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $this->values ??= Setting::query()->pluck('value', 'key')->all();

        $value = $this->values[$key] ?? null;

        return $value === null || $value === '' || $value === [] ? $default : $value;
    }
}
