<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The prototype's answer about returns asked for things back „w stanie nienaruszonym” and counted 14 days
 * for sending them back. Consumer law gives 14 days to withdraw, counted from receiving the parcel, then
 * 14 more to send the thing back, and testing it like in a shop is allowed. Replaces the answer only
 * where nobody has rewritten it in the panel yet.
 */
return new class extends Migration
{
    private const OUTDATED = 'w stanie nienaruszonym';

    private const ANSWER = 'Masz 14 dni od odebrania paczki, żeby odstąpić od umowy bez podawania przyczyny — wystarczy mail. Rzecz odsyłasz na swój koszt w kolejnych 14 dniach, a ja zwracam całą płatność razem z wysyłką do Ciebie, do ceny najtańszej opcji. Nie dotyczy to zamówień indywidualnych i kubków z Twoim tekstem, bo powstały tylko dla Ciebie.';

    public function up(): void
    {
        $stored = DB::table('settings')->where('key', 'faq_items')->value('value');
        $items = is_string($stored) ? json_decode($stored, true) : null;

        if (! is_array($items)) {
            return;
        }

        $corrected = array_map(
            fn (mixed $item) => is_array($item) && is_string($item['answer'] ?? null) && str_contains($item['answer'], self::OUTDATED)
                ? [...$item, 'answer' => self::ANSWER]
                : $item,
            $items,
        );

        if ($corrected !== $items) {
            DB::table('settings')->where('key', 'faq_items')->update(['value' => json_encode($corrected), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // The old answer was wrong, so there is nothing to bring back.
    }
};
