<?php

// „Podaruj wybór” (MA-235): the gift choice card in the configurator, the cart and the page where it is used.
// Polish only — the English pages have no gift choice.
return [
    'line' => [
        'name' => 'Kubek z napisem — wybiera obdarowana',
        'for' => 'dla: :name',
        'from' => 'od: :name',
        'by_email' => 'karta e-mailem :date',
        'print' => 'karta do druku',
        'added' => 'Karta wyboru w koszyku',
    ],

    // The configurator's switch and fields.
    'configurator' => [
        'switch' => 'Niech wybierze obdarowana',
        'switch_hint' => 'Płacisz za rozmiar i dostawę. Napis, kolor wnętrza i adres poda osoba, która dostanie kartę.',
        'own' => 'Sama wpiszę napis',
        'for' => 'Dla kogo',
        'for_placeholder' => 'np. Ania',
        'for_hint' => 'Nieobowiązkowe — puste pole zostawi na karcie linię do wpisania',
        'from' => 'Od kogo',
        'from_placeholder' => 'np. Kasia i Tomek',
        'dedication' => 'Dedykacja',
        'dedication_placeholder' => 'Kilka słów od Ciebie',
        'dedication_hint' => 'Trafi na kartę i do maila',
        'delivery' => 'Jak przekazać kartę',
        'print' => 'Wydrukuję ją sama',
        'print_hint' => 'Dostaniesz PDF mailem zaraz po płatności',
        'email' => 'Wyślij e-mailem w wybranym dniu',
        'email_hint' => 'Wyślę ją rano tego dnia, a Tobie kopię od razu',
        'recipient_email' => 'E-mail osoby, która dostanie kartę',
        'send_on' => 'Dzień wysyłki',
        'terms' => 'Na wybór napisu jest :days dni od płatności, a przy wysyłce e-mailem od dnia wysyłki. Przed końcem terminu dwa razy przypomnę.',
        'price' => ':mug za kubek + :delivery za dostawę do obdarowanej',
        'add' => 'Dodaj kartę do koszyka',
    ],

    'validation' => [
        'recipient_name_max' => 'Imię na karcie zmieszczę do :max znaków',
        'sender_name_max' => 'Podpis „od kogo” zmieszczę do :max znaków',
        'dedication_max' => 'Dedykację zmieszczę do :max znaków',
        'delivery' => 'Wybierz, jak przekazać kartę — wydruk albo e-mail',
        'recipient_email_required' => 'Wpisz e-mail osoby, która dostanie kartę — bez niego karty nie wyślę',
        'recipient_email' => 'Adres e-mail bez małpy — sprawdź, czy nie uciekła',
        'send_on_required' => 'Wybierz dzień, w którym mam wysłać kartę',
        'send_on' => 'Wybierz dzień od dziś do roku naprzód',
    ],

    // The page where the card is used.
    'page' => [
        'title' => 'Wybierz napis na kubku z karty wyboru',
        'description' => 'Masz kartę wyboru z MellowAury? Wpisz kod, wymyśl napis, wybierz kolor wnętrza i podaj adres — dostawa jest już opłacona.',
        'eyebrow' => 'karta wyboru',
        'heading' => 'Twój kubek, Twój napis',
        'lead' => 'Wpisz kod z karty albo z maila. Potem wymyślisz napis, wybierzesz kolor wnętrza i podasz adres — dostawa jest już opłacona.',
        'code' => 'Kod karty',
        'code_hint' => 'np. MA-7KQ2-9XHT — wielkość liter i myślniki nie mają znaczenia',
        'find' => 'Przejdź do wyboru napisu',
        'choose_heading' => 'Wymyśl swój napis',
        'choose_heading_named' => ':name, wymyśl swój napis',
        'from' => 'od: :name',
        'until' => 'Wybór do :date. Dostawa jest już opłacona.',
        'your_text' => 'Twój napis',
        'address' => 'Dokąd wysłać kubek',
        'address_lead' => 'Kubek ulepię, wbiję napis, wypalę i wyślę. E-mail i telefon są do powiadomień o paczce.',
        'free' => 'opłacona',
        'check' => 'Sprawdź napis jeszcze raz — po zamówieniu wbiję go dokładnie tak, litera po literze.',
        'submit' => 'Zamawiam ten kubek',
        'privacy' => 'Dane biorę tylko do wysyłki tego kubka.',
        'chosen_heading' => 'Kubek wybrany',
        'chosen_lead' => 'Dziękuję! Zaczynam lepić. Potwierdzenie przyszło na Twój e-mail, a gdy kubek wyjdzie z pieca, dam znać z numerem paczki.',
        'chosen_text' => 'Napis',
        'chosen_glaze' => 'Wnętrze',
        'closed_heading' => 'Tej karty nie da się już użyć',
        'write' => 'Napisz do mnie: :email — znajdziemy rozwiązanie.',
        'shop' => 'Zobacz kubki w sklepie',
    ],

    'refusal' => [
        'empty' => 'Wpisz kod z karty — 8 znaków po „MA-”',
        'unknown' => 'Nie znam tego kodu. Sprawdź, czy nie pomyliła się litera — kod ma 8 znaków po „MA-”.',
        'chosen' => 'Kubek z tej karty jest już wybrany i zamówiony.',
        'expired' => 'Termin na wybór z tej karty minął.',
    ],
];
