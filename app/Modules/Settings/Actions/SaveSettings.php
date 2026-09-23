<?php

namespace App\Modules\Settings\Actions;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;

/**
 * How other modules change settings: their panel screens call this instead of writing to the table.
 */
class SaveSettings
{
    public function __construct(private Settings $settings) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function __invoke(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->settings->refresh();
    }
}
