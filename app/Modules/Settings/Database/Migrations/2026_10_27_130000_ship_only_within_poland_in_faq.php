<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The FAQ promised shipping to the EU before the checkout can take a foreign address and count WSTO turnover
 * (MA-221, MA-128, MA-121). The answer changes only while it still reads as seeded; one rewritten in the panel stays.
 */
return new class extends Migration
{
    private const SEEDED = 'Tak, do Unii Europejskiej. Napisz przed zamówieniem — policzę wysyłkę dla Twojego kraju i dobiorę opakowanie do dystansu.';

    private const CORRECTED = 'Na razie wysyłam tylko w Polsce.';

    public function up(): void
    {
        $this->replace(self::SEEDED, self::CORRECTED);
    }

    public function down(): void
    {
        $this->replace(self::CORRECTED, self::SEEDED);
    }

    private function replace(string $from, string $to): void
    {
        $stored = DB::table('settings')->where('key', 'faq_items')->value('value');
        $items = is_string($stored) ? json_decode($stored, true) : null;

        if (! is_array($items)) {
            return;
        }

        $corrected = array_map(function (mixed $item) use ($from, $to) {
            if (is_array($item) && ($item['answer'] ?? null) === $from) {
                $item['answer'] = $to;
            }

            return $item;
        }, $items);

        if ($corrected !== $items) {
            DB::table('settings')->where('key', 'faq_items')->update(['value' => json_encode($corrected), 'updated_at' => now()]);
        }
    }
};
