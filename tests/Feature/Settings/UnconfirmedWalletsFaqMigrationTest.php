<?php

namespace Tests\Feature\Settings;

use App\Modules\Settings\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnconfirmedWalletsFaqMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'app/Modules/Settings/Database/Migrations/2026_10_26_120000_drop_unconfirmed_wallets_from_faq.php';

    public function test_the_seeded_answer_stops_promising_wallets_and_a_rewritten_one_stays(): void
    {
        Setting::create(['key' => 'faq_items', 'value' => [
            ['question' => 'Czy mogę zapłacić BLIK-iem?', 'answer' => 'Tak, BLIK jest pierwszą opcją w koszyku. Poza tym: Przelewy24, karta, Apple Pay, Google Pay i zwykły przelew.'],
            ['question' => 'A Apple Pay?', 'answer' => 'Kasia pisze to po swojemu: Apple Pay i Google Pay.'],
        ]]);

        (require base_path(self::MIGRATION))->up();

        $this->assertSame([
            'Tak, BLIK jest pierwszą opcją w koszyku. Poza tym: Przelewy24, karta i zwykły przelew.',
            'Kasia pisze to po swojemu: Apple Pay i Google Pay.',
        ], array_column(Setting::find('faq_items')->value, 'answer'));
    }
}
