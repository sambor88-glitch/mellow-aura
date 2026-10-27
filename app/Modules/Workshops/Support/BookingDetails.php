<?php

namespace App\Modules\Workshops\Support;

use App\Modules\Checkout\Models\Order;
use App\Modules\Monitoring\Support\Alerts;
use App\Modules\Settings\Settings;
use App\Modules\Workshops\Models\WorkshopBooking;
use App\Modules\Workshops\Models\WorkshopDate;

/**
 * What the workshop e-mails show, built from the order's own copy of the name and the price, so a later change
 * in the price list never rewrites a sent confirmation. The exact studio address goes out only here, never on the site.
 */
class BookingDetails
{
    public function __construct(private Settings $settings, private Alerts $alerts) {}

    /**
     * @return array{
     *     booking: WorkshopBooking,
     *     order: Order,
     *     date: WorkshopDate,
     *     workshop: string,
     *     address: ?string,
     *     whatToBring: list<string>,
     *     contactEmail: ?string,
     *     contactPhone: ?string,
     *     city: ?string,
     * }
     */
    public function for(WorkshopBooking $booking): array
    {
        $order = $booking->order->loadMissing('items');

        return [
            'booking' => $booking,
            'order' => $order,
            'date' => $booking->workshopDate,
            'workshop' => (string) $order->items->sortBy('id')->first()?->product_name,
            'address' => $this->address($order->number),
            'whatToBring' => array_values(array_filter((array) $this->settings->get('workshop_what_to_bring', []), 'filled')),
            'contactEmail' => $this->settings->get('contact_email'),
            'contactPhone' => $this->settings->get('contact_phone'),
            'city' => $this->settings->get('footer_city'),
        ];
    }

    /**
     * The address from the panel. Without it the mail still goes, saying the address follows, and Kasia hears
     * that she has to send it herself and fill in the field.
     */
    private function address(string $orderNumber): ?string
    {
        $address = $this->settings->get('studio_address');

        if (blank($address)) {
            $this->alerts->send(
                'workshop-studio-address',
                'Brak adresu pracowni w mailu o warsztacie',
                'Mail o warsztacie do zamówienia '.$orderNumber.' poszedł bez adresu, bo w panelu (Ustawienia → Dane pracowni) '
                    .'pole „Dokładny adres — tylko do maila po zapisie” jest puste. Uzupełnij je i wyślij adres tej osobie sama.',
            );

            return null;
        }

        return trim((string) $address);
    }
}
