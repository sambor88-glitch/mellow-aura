<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * How Kasia packs and ships (confirmed 24.09.2026: every piece protected with packing paper and wood wool or other
 * padding, InPost, a tracking number by e-mail) — a working draft in her voice for the shipping page, in Polish
 * and in English, editable in the panel under Treści. The empty „Pakowanie” tile on the about page gets the short
 * version. Only a missing key or an empty tile is filled; an empty database is left to the seeder.
 */
return new class extends Migration
{
    private const TEXTS = [
        'text_packing_steps' => "Każdą rzecz owijam osobno w papier pakowy.\nWolne miejsce w kartonie wypełniam wiórami albo innym wypełnieniem, żeby nic się nie przesuwało w drodze.\nPaczkę nadaję w InPoście.\nKiedy wyjedzie z pracowni, dostajesz mailem numer do śledzenia.",
        'text_packing_steps_en' => "I wrap every piece separately in packing paper.\nAny empty space in the box gets wood wool or other padding, so nothing moves on the way.\nI send the parcel with InPost.\nOnce it leaves the studio, you get an email with a tracking number.",
    ];

    private const TILE = 'Papier pakowy i wióry albo inne wypełnienie. Wysyłam InPostem, z numerem do śledzenia.';

    public function up(): void
    {
        if (! DB::table('settings')->exists()) {
            return;
        }

        foreach (self::TEXTS as $key => $text) {
            DB::table('settings')->insertOrIgnore(['key' => $key, 'value' => json_encode($text, JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);
        }

        $tiles = json_decode((string) DB::table('settings')->where('key', 'material_tiles')->value('value'), true);

        if (is_array($tiles)) {
            $filled = array_map(fn (mixed $tile) => is_array($tile) && ($tile['title'] ?? null) === 'Pakowanie' && blank($tile['text'] ?? null)
                ? [...$tile, 'text' => self::TILE]
                : $tile, $tiles);

            if ($filled !== $tiles) {
                DB::table('settings')->where('key', 'material_tiles')->update(['value' => json_encode($filled, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        // The texts may have been rewritten in the panel since, so they stay.
    }
};
