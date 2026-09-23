<?php

namespace Tests\Feature\Shared;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use App\Modules\Shared\Support\DispatchTime;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchTimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_days_from_the_panel_read_as_polish_words(): void
    {
        $this->assertSame([[3, 5], '3–5 dni roboczych', '3–5 dni'], $this->dispatch(3, 5));
        $this->assertSame([[2, 4], '2–4 dni robocze', '2–4 dni'], $this->dispatch(2, 4));
        $this->assertSame([[1, 1], '1 dzień roboczy', '1 dzień'], $this->dispatch(1, 1));
        $this->assertSame([[1, 2], '1–2 dni robocze', '1–2 dni'], $this->dispatch(1, 2));
        $this->assertSame([[10, 12], '10–12 dni roboczych', '10–12 dni'], $this->dispatch(10, 12));
        $this->assertSame([[14, 22], '14–22 dni robocze', '14–22 dni'], $this->dispatch(14, 22));
    }

    public function test_one_end_stands_for_both_and_the_ends_come_in_order(): void
    {
        $this->assertSame([[5, 5], '5 dni roboczych', '5 dni'], $this->dispatch(null, 5));
        $this->assertSame([[3, 3], '3 dni robocze', '3 dni'], $this->dispatch(3, null));
        $this->assertSame([[3, 5], '3–5 dni roboczych', '3–5 dni'], $this->dispatch(5, 3));
    }

    public function test_without_days_in_the_panel_nothing_is_promised(): void
    {
        $this->assertSame([null, null, null], $this->dispatch(null, null));
    }

    /**
     * @return array{?array{int, int}, ?string, ?string}
     */
    private function dispatch(?int $min, ?int $max): array
    {
        Setting::query()->whereIn('key', ['dispatch_days_min', 'dispatch_days_max'])->delete();
        Setting::create(['key' => 'dispatch_days_min', 'value' => $min]);
        Setting::create(['key' => 'dispatch_days_max', 'value' => $max]);
        $settings = new Settings;

        return [DispatchTime::days($settings), DispatchTime::label($settings), DispatchTime::label($settings, short: true)];
    }
}
