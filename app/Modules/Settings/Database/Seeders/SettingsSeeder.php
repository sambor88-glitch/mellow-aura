<?php

namespace App\Modules\Settings\Database\Seeders;

use App\Modules\Settings\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Settings taken from the prototype (settings.json). It only adds missing keys,
 * so values changed in the panel stay.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $settings = json_decode(file_get_contents(__DIR__.'/settings.json'), true, flags: JSON_THROW_ON_ERROR);

        foreach ($settings as $setting) {
            Setting::firstOrCreate(['key' => $setting['key']], ['value' => $setting['value']]);
        }
    }
}
