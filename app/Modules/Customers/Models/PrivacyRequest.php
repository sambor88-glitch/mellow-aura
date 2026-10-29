<?php

namespace App\Modules\Customers\Models;

use App\Modules\Customers\Enums\PrivacyRequestType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A GDPR request carried out from „Klientki”. It names the person only by CustomerKey.
 *
 * @property PrivacyRequestType $type
 * @property string $customer_key
 * @property string $performed_by
 * @property array<string, int> $summary
 */
#[Fillable(['type', 'customer_key', 'performed_by', 'summary'])]
class PrivacyRequest extends Model
{
    /** How each count of the summary reads in the panel. */
    private const SUMMARY_LABELS = [
        'orders' => 'zamówienia',
        'paid_orders' => 'opłacone, zostają do księgowości',
        'withdrawals' => 'odstąpienia',
        'received_complaints' => 'zgłoszone reklamacje',
        'complaints' => 'odpowiedzi na reklamacje',
        'vouchers' => 'vouchery',
        'shipments' => 'paczki',
        'custom_orders' => 'zamówienia indywidualne',
        'firing_requests' => 'zgłoszenia do wypału',
        'failed_mails' => 'niewysłane maile',
        'workshop_bookings' => 'zapisy na warsztaty',
    ];

    /**
     * „zamówienia: 2 · zgłoszenia do wypału: 1”, leaving out what was not there. The order is SUMMARY_LABELS',
     * since MySQL keeps a JSON column's keys in its own order.
     */
    public function summaryLabel(): string
    {
        return collect(self::SUMMARY_LABELS)
            ->filter(fn (string $label, string $key) => ($this->summary[$key] ?? 0) > 0)
            ->map(fn (string $label, string $key) => $label.': '.$this->summary[$key])
            ->join(' · ') ?: 'nic nie było zapisane';
    }

    protected function casts(): array
    {
        return [
            'type' => PrivacyRequestType::class,
            'summary' => 'array',
        ];
    }
}
