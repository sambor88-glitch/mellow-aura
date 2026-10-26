<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Decided on 16.09.2026: a custom order is paid in full once the free sketch and quote are accepted, a big one
 * in two instalments. Photos of the finished piece come before shipping, and a piece cracked in the kiln is made
 * again or refunded. Rewrites the seeded steps on /zamowienia-indywidualne, but only steps nobody has edited.
 */
return new class extends Migration
{
    /** @var list<array{string, string, string}> field, seeded text, corrected text */
    private const CHANGES = [
        ['text', 'Do pięciu dni roboczych. Jeśli coś jest technicznie niemożliwe, powiem to od razu.', 'Do pięciu dni roboczych i za darmo. Jeśli coś jest technicznie niemożliwe, powiem to od razu.'],
        ['title', 'Zaliczka BLIK-iem', 'Akceptujesz i płacisz BLIK-iem'],
        ['text', 'Resztę płacisz po zobaczeniu zdjęć gotowej pracy, przed wysyłką.', 'Całość po akceptacji szkicu i ceny. Duże zamówienie, np. serwis na wesele, możesz zapłacić w dwóch ratach: połowę na start, resztę przed wysyłką.'],
        ['text', 'Około czterech tygodni. Przy dużych serwisach dłużej — termin podam w wycenie.', 'Około czterech tygodni, przy dużych serwisach dłużej. Przed wysyłką dostajesz zdjęcia gotowej pracy. Jeśli coś pęknie w piecu, robię od nowa albo oddaję całą wpłatę.'],
    ];

    public function up(): void
    {
        $stored = DB::table('settings')->where('key', 'custom_order_steps')->value('value');
        $steps = is_string($stored) ? json_decode($stored, true) : null;

        if (! is_array($steps)) {
            return;
        }

        $corrected = array_map(function (mixed $step) {
            foreach (self::CHANGES as [$field, $seeded, $correct]) {
                if (is_array($step) && ($step[$field] ?? null) === $seeded) {
                    $step[$field] = $correct;
                }
            }

            return $step;
        }, $steps);

        if ($corrected !== $steps) {
            DB::table('settings')->where('key', 'custom_order_steps')->update(['value' => json_encode($corrected), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The deposit is no longer offered, so there is nothing to bring back.
    }
};
