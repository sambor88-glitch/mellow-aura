<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const OLD = 'Twój własny tekst do 22 znaków bez dopłaty.';

    private const NEW = 'Twój własny napis wbiję na zamówienie — wpiszesz go w konfiguratorze kubka.';

    /**
     * The quote mugs no longer sell "Twój tekst" (MA-275), so their description points to the mug configurator.
     * Only the sentence from the seeder changes: a description Kasia rewrote in the panel stays hers.
     */
    public function up(): void
    {
        $this->swap(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->swap(self::NEW, self::OLD);
    }

    private function swap(string $from, string $to): void
    {
        $product = DB::table('products')->where('slug', 'kubki-z-cytatem')->first(['id', 'description']);

        if ($product !== null && str_contains((string) $product->description, $from)) {
            DB::table('products')->where('id', $product->id)->update(['description' => str_replace($from, $to, $product->description)]);
        }
    }
};
