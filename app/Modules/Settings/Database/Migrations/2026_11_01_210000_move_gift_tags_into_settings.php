<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * „Dla kogo” and the occasions of „Szukam prezentu” move from the code into the panel (MA-352), with the keys the
 * products are already tagged with, so nothing loses its tags. An empty database is left to the seeder, and a list
 * already saved stays.
 */
return new class extends Migration
{
    private const LISTS = [
        'gift_recipients' => [
            ['key' => 'for_her', 'label' => 'Dla niej'],
            ['key' => 'for_him', 'label' => 'Dla niego / taty'],
            ['key' => 'for_couple', 'label' => 'Dla pary'],
        ],
        'gift_occasions' => [
            ['key' => 'birthday', 'label' => 'Urodziny'],
            ['key' => 'mothers_day', 'label' => 'Dzień Matki'],
            ['key' => 'wedding', 'label' => 'Ślub'],
            ['key' => 'housewarming', 'label' => 'Parapetówka'],
            ['key' => 'anniversary', 'label' => 'Rocznica'],
            ['key' => 'christmas', 'label' => 'Święta'],
            ['key' => 'self_gift', 'label' => 'Dla siebie'],
        ],
    ];

    public function up(): void
    {
        if (! DB::table('settings')->exists()) {
            return;
        }

        foreach (self::LISTS as $key => $tags) {
            if (DB::table('settings')->where('key', $key)->exists()) {
                continue;
            }

            DB::table('settings')->insert(['key' => $key, 'value' => json_encode($tags, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The lists may have been changed since, so they stay.
    }
};
