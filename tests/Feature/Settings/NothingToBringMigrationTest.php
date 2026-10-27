<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Database\Seeders\SettingsSeeder;
use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NothingToBringMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_seeded_texts_stop_promising_nothing_to_bring_and_rewritten_ones_stay(): void
    {
        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_10_27_110000_drop_nothing_to_bring_from_workshop_texts.php');

        $this->seed(SettingsSeeder::class);
        Setting::query()->whereKey('text_workshops_lead')->update(['value' => json_encode('Ceny zawierają glinę, narzędzia, szkliwa i wypały. Nie musisz nic umieć ani nic przynosić.')]);
        $notes = Setting::find('voucher_workshop_notes')->value;
        $notes['voucher-lepienie']['preparation'] = 'Nie musisz nic umieć ani nic przynosić. Glina, narzędzia, herbata i coś słodkiego czekają na miejscu.';
        $notes['voucher-para']['preparation'] = 'Własny tekst Kasi.';
        Setting::query()->whereKey('voucher_workshop_notes')->update(['value' => json_encode($notes)]);

        $migration->up();
        $migration->up();

        $this->assertSame('Ceny zawierają glinę, narzędzia, szkliwa i wypały. Nie musisz nic umieć — wszystko, czego potrzebujesz do pracy, czeka w pracowni.', Setting::find('text_workshops_lead')->value);
        $notes = Setting::find('voucher_workshop_notes')->value;
        $this->assertSame('Nie musisz nic umieć. Glina, narzędzia, herbata i coś słodkiego czekają na miejscu.', $notes['voucher-lepienie']['preparation']);
        $this->assertSame('Własny tekst Kasi.', $notes['voucher-para']['preparation']);
        $this->assertSame('Dwie godziny przy stole w mojej pracowni, w grupie do sześciu osób.', $notes['voucher-lepienie']['expect']);
    }
}
