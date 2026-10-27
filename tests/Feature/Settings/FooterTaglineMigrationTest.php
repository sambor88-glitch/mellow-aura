<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FooterTaglineMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migrate(): void
    {
        $migration = require base_path('app/Modules/Settings/Database/Migrations/2026_10_27_120000_put_the_footer_tagline_in_polish.php');
        $migration->up();
        app(Settings::class)->refresh();
    }

    public function test_the_seeded_english_line_turns_polish(): void
    {
        Setting::create(['key' => 'text_footer_tagline', 'value' => 'A little bit of magic. Home studio in Cracow. Ceramic & jewelry. Less waste silk scrunchies.']);

        $this->migrate();

        $this->assertSame('Domowa pracownia w Krakowie. Ceramika formowana w dłoniach i jedwabne dodatki z drugiego obiegu.', app(Settings::class)->get('text_footer_tagline'));
    }

    public function test_a_line_kasia_rewrote_stays(): void
    {
        Setting::create(['key' => 'text_footer_tagline', 'value' => 'Ceramika z Krakowa.']);

        $this->migrate();

        $this->assertSame('Ceramika z Krakowa.', app(Settings::class)->get('text_footer_tagline'));
    }
}
