<?php

namespace Database\Seeders;

use App\Modules\Gifts\Models\Voucher;
use App\Modules\Workshops\Models\WorkshopDate;
use Illuminate\Database\Seeder;

/**
 * Data for the browser tests (MA-263), seeded by tests/e2e/serve.sh only — never on staging or production.
 * Each screen size gets its own amount vouchers and its own two dates of „Rodzinnie z dzieckiem”,
 * so the desktop run leaves nothing the phone run would trip over.
 */
class E2eSeeder extends Seeder
{
    /** Amount vouchers per screen size, in grosze: one pays a whole order, one a part, one a workshop. */
    public const VOUCHERS = ['AAA2' => 500000, 'AAA3' => 2000, 'AAA4' => 100000];

    /** The prefix in the codes, e.g. MA-DESK-AAA2; tests/e2e/vouchers.spec.js builds the same codes. */
    public const SCREENS = ['DESK', 'PHNE'];

    public function run(): void
    {
        foreach (self::SCREENS as $screen) {
            foreach (self::VOUCHERS as $suffix => $amount) {
                Voucher::query()->create([
                    'code' => "MA-{$screen}-{$suffix}",
                    'amount_minor' => $amount,
                    'balance_minor' => $amount,
                    'valid_until' => now()->addYear()->toDateString(),
                ]);
            }
        }

        // Far after the dates tests/e2e/workshops.spec.js adds, the desktop's two first, then the phone's.
        foreach ([20, 21, 22, 23] as $days) {
            WorkshopDate::query()->create([
                'workshop_code' => 'parent_and_child',
                'starts_at' => now()->addDays($days)->setTime(10, 0),
                'ends_at' => now()->addDays($days)->setTime(12, 0),
                'seats' => 4,
            ]);
        }
    }
}
