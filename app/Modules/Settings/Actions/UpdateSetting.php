<?php

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;

class UpdateSetting
{
    public function __construct(private Settings $settings) {}

    public function handle(string $key, mixed $value): Setting
    {
        $setting = Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);

        $this->settings->flush();

        return $setting;
    }
}
