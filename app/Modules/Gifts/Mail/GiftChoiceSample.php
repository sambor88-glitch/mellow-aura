<?php

namespace App\Modules\Gifts\Mail;

use App\Modules\Gifts\Models\GiftChoice;

/**
 * A made-up gift choice card for the previews of its mails in the panel. Nothing is saved.
 */
class GiftChoiceSample
{
    public static function make(): GiftChoice
    {
        return (new GiftChoice)->forceFill([
            'code' => 'MA-7KQ2-9XHT',
            'size_label' => 'Średni',
            'size_name' => 'Średni 300 ml',
            'price_gross' => 16400,
            'recipient_name' => 'Ola',
            'sender_name' => 'Anna',
            'dedication' => 'Napisz, co chcesz — byle z kawą.',
            'recipient_email' => 'ola@example.com',
            'send_on' => now()->addWeek()->toDateString(),
            'choose_by' => now()->addWeek()->addDays(60)->toDateString(),
        ]);
    }
}
