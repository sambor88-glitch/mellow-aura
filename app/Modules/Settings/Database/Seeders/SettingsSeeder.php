<?php

namespace App\Modules\Settings\Database\Seeders;

use App\Modules\Settings\Models\Setting;
use App\Modules\Settings\Settings;
use Illuminate\Database\Seeder;

/**
 * Starting values from the prototype (CFG_SEED, MATERIALS_SEED).
 *
 * Existing keys are never overwritten, so re-running the seeder keeps what
 * Kasia changed in the panel. Private contact data stays empty on purpose:
 * it is filled in the panel, not committed to the public repository.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->defaults() as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        app(Settings::class)->flush();
    }

    /**
     * Amounts in grosze.
     *
     * @return array<string, mixed>
     */
    private function defaults(): array
    {
        return [
            'shipping.free_threshold' => 40000,
            'shipping.parcel_locker_price' => 1600,
            'shipping.courier_price' => 2200,
            'shipping.gift_wrap_price' => 1200,
            'custom_orders.deposit' => 8000,
            'custom_orders.deposit_enabled' => false,

            'contact.email' => '',
            'contact.phone' => '',
            'contact.instagram' => 'mellowaura.ceramika',
            'contact.facebook_url' => 'https://facebook.com/profile.php?id=61552376291595',
            'contact.google_business_url' => '',

            'location.description' => 'Kraków, okolice Błoń Krakowskich',
            'location.short' => 'Kraków, Polska',
            // Sent only in the e-mail after a workshop sign-up, never shown on the site.
            'location.studio_address' => '',
            'location.parcel_locker_code' => '',

            'mug.text_max_length' => 22,

            'texts.footer_tagline' => 'A little bit of magic. Home studio in Cracow. Ceramic & jewelry. Less waste silk scrunchies.',

            // Material tiles on the product page; an empty description is not shown.
            'materials.tiles' => [
                ['title' => 'Glina', 'description' => 'Kamionka i szamot piaskowy. Nierówne krawędzie zostawiam, bo tak wygląda ręka.'],
                ['title' => 'Jedwab', 'description' => 'Z drugiego obiegu i z końcówek bel. Jedna apaszka to jedna opaska.'],
                ['title' => 'Złoto 24k', 'description' => 'Prawdziwy metal, trzeci wypał. Dlatego złocone rzeczy myje się ręcznie.'],
                ['title' => 'Rośliny', 'description' => 'Zbierane pod pracownią, odciskane w surowej glinie i wypalane na popiół.'],
                ['title' => 'Temperatura wypału', 'description' => ''],
                ['title' => 'Zmywarka', 'description' => ''],
                ['title' => 'Mikrofalówka', 'description' => ''],
                ['title' => 'Atest szkliw', 'description' => ''],
                ['title' => 'Pakowanie', 'description' => ''],
            ],
        ];
    }
}
