<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The FAQ promised Apple Pay and Google Pay before Przelewy24 has switched them on for the shop (MA-99, MA-98).
 * The answer changes only while it still reads as seeded; one rewritten in the panel stays as it is.
 */
return new class extends Migration
{
    private const SEEDED = 'Poza tym: Przelewy24, karta, Apple Pay, Google Pay i zwykły przelew.';

    private const CORRECTED = 'Poza tym: Przelewy24, karta i zwykły przelew.';

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
            if (is_array($item) && is_string($item['answer'] ?? null) && str_ends_with($item['answer'], $from)) {
                $item['answer'] = substr($item['answer'], 0, -strlen($from)).$to;
            }

            return $item;
        }, $items);

        if ($corrected !== $items) {
            DB::table('settings')->where('key', 'faq_items')->update(['value' => json_encode($corrected), 'updated_at' => now()]);
        }
    }
};
