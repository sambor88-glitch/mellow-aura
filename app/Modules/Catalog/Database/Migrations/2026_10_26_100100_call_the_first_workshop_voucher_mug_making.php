<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Kasia's first workshop is mug making for 2 hours (MA-192), so its voucher stops promising two and a half hours
 * of hand building. The slug stays, so links and orders keep working; a name or text changed in the panel stays.
 */
return new class extends Migration
{
    private const CHANGES = [
        'name' => ['Voucher na warsztat — lepienie z ręki', 'Voucher na warsztat — lepienie kubków'],
        'description' => [
            'Dwie i pół godziny przy stole w mojej pracowni, glina, narzędzia i dwa wypały w cenie. Voucher dostajesz w PDF do wydruku albo wyślę go pocztą na ładnym papierze. Ważny rok — termin obdarowana osoba wybiera sama.',
            'Dwie godziny przy stole w mojej pracowni, glina, narzędzia i dwa wypały w cenie. Voucher dostajesz w PDF do wydruku albo wyślę go pocztą na ładnym papierze. Ważny rok — termin obdarowana osoba wybiera sama.',
        ],
    ];

    public function up(): void
    {
        foreach (self::CHANGES as $column => [$seeded, $correct]) {
            DB::table('products')->where('slug', 'voucher-lepienie')->where($column, $seeded)->update([$column => $correct, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The seeded texts were not true, so there is nothing to bring back.
    }
};
