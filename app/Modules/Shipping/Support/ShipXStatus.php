<?php

namespace App\Modules\Shipping\Support;

/**
 * What each ShipX status means for the order (MA-133), with the words Kasia reads on the „InPost” card.
 * The list is ShipX's own, from GET /v1/statuses (25.10.2026). A status that is not here moves nothing.
 */
final class ShipXStatus
{
    /** Kasia has the label; the parcel has not left her hands yet. */
    public const PREPARING = 'preparing';

    /** The parcel has left the studio: the order is „Wysłane”. Waiting in a locker counts too. */
    public const ON_THE_WAY = 'on_the_way';

    /** The customer has it: the order is „Zakończone”. */
    public const DELIVERED = 'delivered';

    /** The parcel is coming back or will not arrive: the order is „Problem”. */
    public const PROBLEM = 'problem';

    /** status => [stage, what Kasia reads] */
    private const STATUSES = [
        'created' => [self::PREPARING, 'InPost przygotowuje etykietę'],
        'offers_prepared' => [self::PREPARING, 'InPost przygotowuje etykietę'],
        'offer_selected' => [self::PREPARING, 'InPost przygotowuje etykietę'],
        'confirmed' => [self::PREPARING, 'Etykieta gotowa — czeka na nadanie'],

        'dispatched_by_sender' => [self::ON_THE_WAY, 'Nadana w paczkomacie'],
        'dispatched_by_sender_to_pok' => [self::ON_THE_WAY, 'Nadana w punkcie InPost'],
        'collected_from_sender' => [self::ON_THE_WAY, 'Kurier ją odebrał'],
        'taken_by_courier' => [self::ON_THE_WAY, 'Kurier ją odebrał'],
        'taken_by_courier_from_pok' => [self::ON_THE_WAY, 'W drodze'],
        'adopted_at_source_branch' => [self::ON_THE_WAY, 'W drodze'],
        'sent_from_source_branch' => [self::ON_THE_WAY, 'W drodze'],
        'adopted_at_sorting_center' => [self::ON_THE_WAY, 'W drodze'],
        'sent_from_sorting_center' => [self::ON_THE_WAY, 'W drodze'],
        'adopted_at_target_branch' => [self::ON_THE_WAY, 'W drodze'],
        'redirect_to_box' => [self::ON_THE_WAY, 'W drodze'],
        'canceled_redirect_to_box' => [self::ON_THE_WAY, 'W drodze'],
        'readdressed' => [self::ON_THE_WAY, 'W drodze — klient zmienił adres'],
        'unstack_from_customer_service_point' => [self::ON_THE_WAY, 'W drodze do paczkomatu'],
        'unstack_from_box_machine' => [self::ON_THE_WAY, 'W drodze do paczkomatu'],
        'delay_in_delivery' => [self::ON_THE_WAY, 'W drodze — możliwe opóźnienie'],
        'oversized' => [self::ON_THE_WAY, 'Nie zmieściła się w skrytce — InPost ją przekieruje'],
        'out_for_delivery' => [self::ON_THE_WAY, 'U kuriera, w doręczeniu'],
        'out_for_delivery_to_address' => [self::ON_THE_WAY, 'U kuriera, w doręczeniu'],
        'pickup_reminder_sent_address' => [self::ON_THE_WAY, 'Kurier nie zastał klienta — jutro kolejna próba'],
        'ready_to_pickup' => [self::ON_THE_WAY, 'Czeka w paczkomacie'],
        'pickup_reminder_sent' => [self::ON_THE_WAY, 'Czeka w paczkomacie'],
        'stack_in_box_machine' => [self::ON_THE_WAY, 'Czeka w paczkomacie zastępczym'],
        'ready_to_pickup_from_pok' => [self::ON_THE_WAY, 'Czeka w punkcie InPost'],
        'ready_to_pickup_from_pok_registered' => [self::ON_THE_WAY, 'Czeka w punkcie InPost'],
        'stack_in_customer_service_point' => [self::ON_THE_WAY, 'Czeka w punkcie InPost'],
        'courier_avizo_in_customer_service_point' => [self::ON_THE_WAY, 'Czeka w punkcie InPost'],
        'ready_to_pickup_from_branch' => [self::ON_THE_WAY, 'Czeka w oddziale InPost'],
        // The customer may still pick it up; a return comes as its own status.
        'pickup_time_expired' => [self::ON_THE_WAY, 'Minął termin odbioru — może do Ciebie wrócić'],
        'stack_parcel_pickup_time_expired' => [self::ON_THE_WAY, 'Minął termin odbioru — może do Ciebie wrócić'],
        'stack_parcel_in_box_machine_pickup_time_expired' => [self::ON_THE_WAY, 'Minął termin odbioru — może do Ciebie wrócić'],

        'delivered' => [self::DELIVERED, 'Doręczona'],

        'returned_to_sender' => [self::PROBLEM, 'Wraca do Ciebie'],
        'taken_by_courier_from_customer_service_point' => [self::PROBLEM, 'Wraca do Ciebie'],
        'return_pickup_confirmation_to_sender' => [self::PROBLEM, 'Wraca do Ciebie'],
        'avizo' => [self::PROBLEM, 'Kurier nie zastał klienta — wraca do Ciebie'],
        'rejected_by_receiver' => [self::PROBLEM, 'Klient nie przyjął paczki'],
        'undelivered' => [self::PROBLEM, 'Niedoręczona — leży w magazynie InPost'],
        'undelivered_wrong_address' => [self::PROBLEM, 'Nie dało się doręczyć — zły adres'],
        'undelivered_incomplete_address' => [self::PROBLEM, 'Nie dało się doręczyć — niepełny adres'],
        'undelivered_unknown_receiver' => [self::PROBLEM, 'Nie dało się doręczyć — nikt nie zna klienta pod adresem'],
        'undelivered_cod_cash_receiver' => [self::PROBLEM, 'Nie dało się doręczyć'],
        'undelivered_no_mailbox' => [self::PROBLEM, 'Nie dało się doręczyć — brak skrzynki'],
        'undelivered_not_live_address' => [self::PROBLEM, 'Nie dało się doręczyć — nikt tam nie mieszka'],
        'undelivered_lack_of_access_letterbox' => [self::PROBLEM, 'Nie dało się doręczyć — brak dostępu do skrzynki'],
        'claimed' => [self::PROBLEM, 'Klient złożył reklamację w InPost'],
        'missing' => [self::PROBLEM, 'InPost nie może jej odnaleźć'],
        'canceled' => [self::PROBLEM, 'Etykieta anulowana — paczka nie wyszła'],
    ];

    /** After these ShipX has nothing more to say about the parcel, so the sync stops asking. */
    public const FINAL = ['delivered', 'returned_to_sender', 'taken_by_courier_from_customer_service_point', 'canceled'];

    public static function stage(string $status): ?string
    {
        return self::STATUSES[$status][0] ?? null;
    }

    public static function label(string $status): string
    {
        return self::STATUSES[$status][1] ?? 'Inny status w InPost — sprawdź w InPost Managerze';
    }
}
