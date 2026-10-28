<?php

namespace App\Modules\Workshops\Mail\Concerns;

use App\Modules\Checkout\Support\SampleOrder;
use App\Modules\Shared\Mail\EditableTexts;
use App\Modules\Workshops\Enums\BookingStatus;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;

/**
 * What the workshop mails fill into the sentences Kasia writes in the panel („Maile”, MA-242), and the made-up
 * booking the panel previews them with.
 */
trait BookingTexts
{
    /**
     * @param  list<string>  $parts
     */
    private static function bookingTexts(string $key, string $label, string $when, array $parts = ['subject', 'intro', 'outro']): EditableTexts
    {
        return new EditableTexts(
            key: $key,
            label: $label,
            when: $when,
            lang: 'workshops::mail-texts.'.$key,
            parts: $parts,
            placeholders: [
                'numer' => 'numer zapisu',
                'termin' => 'dzień i godziny warsztatu',
                'dzien' => 'sam dzień',
                'godzina' => 'godzina początku',
            ],
        );
    }

    /**
     * @return array{numer: string, termin: string, dzien: string, godzina: string}
     */
    protected function textFill(): array
    {
        $date = $this->booking->workshopDate;

        return [
            'numer' => $this->booking->order->number,
            'termin' => $date->dayLabel().', '.$date->timeLabel(),
            'dzien' => $date->dayLabel(),
            'godzina' => $date->starts_at->format('H:i'),
        ];
    }

    private static function sampleBooking(): WorkshopBooking
    {
        $order = SampleOrder::make('pl');
        $order->items->first()->product_name = 'Warsztat na kole garncarskim';
        $starts = now()->addWeek()->setTime(11, 0)->toImmutable();

        return (new WorkshopBooking)->forceFill([
            'seats' => 2,
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
        ])
            ->setRelation('order', $order)
            ->setRelation('voucher', null)
            ->setRelation('workshopDate', (new WorkshopDate)->forceFill(['starts_at' => $starts, 'ends_at' => $starts->addMinutes(150), 'seats' => 8]));
    }
}
