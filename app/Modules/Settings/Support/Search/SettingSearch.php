<?php

namespace App\Modules\Settings\Support\Search;

use App\Modules\Admin\Support\Search\PanelSearch;
use App\Modules\Admin\Support\Search\SearchProvider;
use App\Modules\Admin\Support\Search\SearchResult;
use App\Modules\Settings\Settings;

/**
 * Single fields of „Ustawienia” in the panel search (MA-308): „prog” finds „Próg darmowej wysyłki”, and Enter opens
 * its tab with the cursor in the field (the #id of the field, resources/js/panel-tabs.js). The ids are the ones in
 * settings::admin.edit; a new field there gets a line here.
 */
final class SettingSearch implements SearchProvider
{
    /** Tab => [field id, label, more words it is found by]. */
    private const FIELDS = [
        'Dostawa i opłaty' => [
            ['dostawa-free-shipping-threshold', 'Próg darmowej wysyłki', 'darmowa wysylka dostawa gratis od'],
            ['dostawa-gift-wrap-price', 'Pakowanie na prezent — cena', 'opakowanie prezentowe'],
            ['dostawa-dispatch-days-min', 'Czas wysyłki rzeczy z półki', 'dni robocze wysylka nadanie'],
        ],
        'Pracownia' => [
            ['pracownia-telefon', 'Telefon (WhatsApp)', 'numer kontakt'],
            ['pracownia-email', 'E-mail na stronie', 'mail adres kontakt'],
            ['pracownia-instagram', 'Instagram — nazwa profilu', 'ig social media'],
            ['pracownia-facebook', 'Facebook — adres strony', 'fb social media'],
            ['pracownia-google', 'Wizytówka Google', 'google maps opinie profil firmy'],
            ['pracownia-okolica', 'Pracownia — okolica na stronie, bez adresu', 'blonia lokalizacja gdzie'],
            ['pracownia-adres', 'Dokładny adres pracowni — tylko do maila po zapisie', 'ulica warsztaty dojazd'],
            ['pracownia-paczkomat', 'Twój Paczkomat — kod dla przesyłek od klientów', 'inpost zwroty nadawanie'],
            ['pracownia-miasto', 'Miasto w stopce', 'krakow stopka'],
        ],
        'Firma' => [
            ['firma-nazwa', 'Firma z CEIDG', 'nazwa firmy jdg dzialalnosc'],
            ['firma-nip', 'NIP', 'firma podatek'],
            ['firma-regon', 'REGON', 'firma'],
            ['firma-rachunek', 'Numer rachunku do przelewów', 'konto bank przelew tradycyjny iban'],
            ['firma-adres', 'Adres firmy i do doręczeń', 'siedziba regulamin'],
            ['firma-zwroty', 'Adres do zwrotów', 'zwrot odstapienie paczkomat'],
            ['firma-operator', 'Operator płatności', 'przelewy24 p24 platnosci'],
            ['firma-vat', 'Informacja o VAT', 'zwolnienie podatek stawka'],
        ],
        'Karta produktu' => [
            ['karta-produktu-roznica', 'Dopuszczalna różnica wymiarów', 'tolerancja wymiary rozmiar'],
            ['karta-produktu-pielegnacja', 'Pielęgnacja ceramiki', 'zmywarka mikrofalowka mycie'],
        ],
        'Statystyki' => [
            ['statystyki-id', 'Identyfikator pomiaru GA4', 'google analytics statystyki pomiar'],
        ],
        'Materiały' => [
            ['materialy', 'Materiały — kafelki na „O mnie”', 'glina szkliwo kafelki o mnie'],
        ],
    ];

    public function group(): string
    {
        return 'Ustawienia';
    }

    public function screen(): string
    {
        return 'admin.settings.edit';
    }

    public function search(string $term, int $limit): iterable
    {
        $found = 0;

        foreach ($this->fields() as [$tab, $id, $label, $words]) {
            if ($found < $limit && PanelSearch::matches($term, $label, $words, $tab)) {
                $found++;

                yield new SearchResult(
                    title: $label,
                    url: route('admin.settings.edit').'#'.$id,
                    route: 'admin.settings.edit',
                    detail: 'Ustawienia → '.$tab,
                    icon: '⚙',
                );
            }
        }
    }

    /**
     * The fields above with the price of each delivery from the panel, right after the threshold.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}>
     */
    private function fields(): array
    {
        $fields = [];

        foreach (self::FIELDS as $tab => $rows) {
            foreach ($rows as [$id, $label, $words]) {
                $fields[] = [$tab, $id, $label, $words];

                if ($id === 'dostawa-free-shipping-threshold') {
                    array_push($fields, ...$this->deliveries($tab));
                }
            }
        }

        return $fields;
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: string}> */
    private function deliveries(string $tab): array
    {
        $fields = [];

        foreach ((array) app(Settings::class)->get('shipping_methods', []) as $method) {
            if (! is_array($method) || blank($method['code'] ?? null) || blank($method['label'] ?? null)) {
                continue;
            }

            $code = str_replace('_', '-', (string) $method['code']);
            $fields[] = [$tab, 'dostawa-shipping-'.$code, $method['label'].' — koszt dostawy', 'cena wysylki dostawa'];
            $fields[] = [$tab, 'dostawa-shipping-eur-'.$code, $method['label'].' — w angielskiej kasie', 'euro eur cena wysylki dostawa'];
        }

        return $fields;
    }
}
