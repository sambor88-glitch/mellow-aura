<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * „Nie musisz nic umieć ani nic przynosić” goes from the workshop texts (decision of Maciej, 27.10.2026): the mail
 * after booking now says what to bring (MA-138, MA-208), so the page and the voucher no longer promise nothing.
 * A text changes only while it still reads as seeded, so anything rewritten in the panel stays as it is.
 */
return new class extends Migration
{
    private const LEAD = [
        'Ceny zawierają glinę, narzędzia, szkliwa i wypały. Nie musisz nic umieć ani nic przynosić.',
        'Ceny zawierają glinę, narzędzia, szkliwa i wypały. Nie musisz nic umieć — wszystko, czego potrzebujesz do pracy, czeka w pracowni.',
    ];

    /** Voucher => [seeded preparation, corrected preparation]. */
    private const PREPARATION = [
        'voucher-lepienie' => [
            'Nie musisz nic umieć ani nic przynosić. Glina, narzędzia, herbata i coś słodkiego czekają na miejscu.',
            'Nie musisz nic umieć. Glina, narzędzia, herbata i coś słodkiego czekają na miejscu.',
        ],
        'voucher-para' => [
            'Nie musicie nic umieć ani nic przynosić. Glina, narzędzia i wino albo herbata czekają na miejscu.',
            'Nie musicie nic umieć. Glina, narzędzia i wino albo herbata czekają na miejscu.',
        ],
    ];

    public function up(): void
    {
        $lead = $this->value('text_workshops_lead');

        if ($lead === self::LEAD[0]) {
            $this->save('text_workshops_lead', self::LEAD[1]);
        }

        $notes = $this->value('voucher_workshop_notes');

        if (! is_array($notes)) {
            return;
        }

        $corrected = $notes;

        foreach (self::PREPARATION as $voucher => [$seeded, $correct]) {
            if (($corrected[$voucher]['preparation'] ?? null) === $seeded) {
                $corrected[$voucher]['preparation'] = $correct;
            }
        }

        if ($corrected !== $notes) {
            $this->save('voucher_workshop_notes', $corrected);
        }
    }

    public function down(): void
    {
        // The old sentence contradicts the mail after booking, so there is nothing to bring back.
    }

    private function value(string $key): mixed
    {
        $value = DB::table('settings')->where('key', $key)->value('value');

        return $value === null ? null : json_decode($value, true);
    }

    private function save(string $key, mixed $value): void
    {
        DB::table('settings')->where('key', $key)->update(['value' => json_encode($value), 'updated_at' => now()]);
    }
};
