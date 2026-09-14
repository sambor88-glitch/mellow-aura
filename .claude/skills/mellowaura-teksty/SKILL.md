---
name: mellowaura-teksty
description: Teksty w interfejsie po polsku — etykiety przycisków, komunikaty błędów, potwierdzenia, podpowiedzi pod polami, stany puste, opisy produktów, nazwy zakładek, maile do klienta. Głos właścicielki, nie firmy. Użyj, gdy trzeba coś nazwać, napisać komunikat albo gdy pada „brzmi korporacyjnie", „napisz to po ludzku", „co ma być na przycisku", „jak to nazwać".
---

# Teksty w interfejsie

Tekst w interfejsie to nie ozdoba nałożona na gotowy ekran — to ta część projektu, którą człowiek
faktycznie czyta w momencie decyzji. Zła etykieta przycisku kosztuje więcej niż zły kolor.

## Dwa głosy, nie jeden

**Do klienta mówi Kasia w pierwszej osobie.** Nie „firma", nie „my", nie strona bierna.

> „Litery wbijam stemplem jedna po drugiej w surową glinę, którego nie zetrze żadna zmywarka."
> „Dane zapisuję tylko na czas realizacji."
> „Przyślij apaszkę babci albo sukienkę, z której wyrosłaś."

**W panelu mówimy do Kasi**, na „Ty", jak asystent, który zna jej pracownię.

> „tylko dla Ciebie · nie widzą tego klienci"
> „Kubek, miska, talerz, wazon, scrunchie, kosmetyczka, piórnik — cokolwiek zrobisz."
> „Wpisz tylko to, co pasuje. Puste pola nie pokażą się na stronie."

Nigdy nie mieszaj tych dwóch w jednym zdaniu.

## Przyciski — nazwij skutek, nie operację

Człowiek klikający przycisk pyta „co się teraz stanie". Odpowiedz na to pytanie w etykiecie.

| Zamiast | Napisz |
| --- | --- |
| Zapisz | Opublikuj w sklepie |
| Wyślij | Wyślij zapytanie — odpiszę w 2 dni |
| Dalej | Płacę 349 zł |
| OK | Rozumiem, zamykam |
| Prześlij plik | Wybierz zdjęcie z telefonu albo komputera |
| Anuluj | Wróć bez zapisywania |

Gdy czegoś brakuje, przycisk **nie jest wyłączony — mówi, czego brakuje**: „Wpisz kod BLIK,
żeby zapłacić". Reguła i kod są w `mellowaura-ux`.

Długość: do czterech słów na przycisku głównym. Dłuższe łamie się na telefonie.

## Błędy — powiedz, co zrobić

Trzy części, w tej kolejności: **co się stało · dlaczego · co teraz**. Trzeciej nigdy nie pomijaj.

| Źle | Dobrze |
| --- | --- |
| Błąd walidacji | Wpisz 6-cyfrowy kod z aplikacji banku |
| Płatność nieudana | Bank odrzucił kod. Spróbuj jeszcze raz albo zapłać przelewem. |
| Pole wymagane | Bez numeru telefonu kurier nie znajdzie paczkomatu |
| Error 500 | Coś się zacięło po mojej stronie. Twój koszyk jest bezpieczny — spróbuj za chwilę. |
| Nieprawidłowy format | Adres e-mail bez małpy — sprawdź, czy nie uciekła |

Czego nie robić: nie obwiniaj („podałeś zły adres"), nie przepraszaj trzy razy, nie pokazuj kodów
technicznych. Kod błędu idzie do panelu, nie na ekran klienta.

## Potwierdzenia — powiedz o skutku

Pigułka po zapisie ma mówić, co się zmieniło w świecie, nie że operacja się powiodła.

| Zamiast | Napisz |
| --- | --- |
| Zapisano pomyślnie | Zapisane. Klienci już to widzą. |
| Dodano produkt | Miska z paprocią — opublikowana w sklepie |
| Operacja zakończona | Termin dodany — widać go na stronie warsztatów |
| Usunięto | Ukryte. Zostaje w historii zamówień. |

## Podpowiedzi pod polami

Podpowiedź stoi tam **zanim** ktoś zacznie pisać i mówi, czego się spodziewasz:

> „JPG lub PNG, najlepiej kwadrat"
> „6 cyfr, ważne 2 minuty"
> „Twój własny tekst do 22 znaków bez dopłaty"
> „Nieobowiązkowe — puste pola nie pokażą się na stronie"

Nie powtarzaj etykiety w podpowiedzi. Jeśli pole nazywa się „Telefon", podpowiedź nie brzmi
„wpisz telefon", tylko mówi, **po co** go zbierasz: „Do powiadomienia z paczkomatu".

## Stany puste

Stan pusty to nie komunikat o braku, tylko zaproszenie. Zawsze dwa zdania i jedno wyjście.

> **Jeszcze nic tu nie ma**
> Pierwsze zamówienie pojawi się tutaj razem z adresem do wysyłki i sposobem płatności.
> [ Dodaj pierwszy produkt ]

Pusty koszyk nigdy nie brzmi „Koszyk jest pusty". Brzmi: „Nic tu jeszcze nie ma. Zobacz, co czeka
w pracowni" + przycisk do sklepu.

## Opisy produktów

Wzorzec, który działa w tym sklepie — konkret warsztatowy zamiast przymiotników:

> „Talerze z odciskiem prawdziwych roślin zebranych pod pracownią — baldachy, gałązki, liście.
> Roślina wypala się w piecu i zostawia po sobie rysunek, którego nie da się powtórzyć."

Trzy rzeczy w każdym opisie: **z czego to jest · jak powstaje · co z tym zrobisz**.
Dwa do czterech zdań. Bez „wyjątkowy", „niepowtarzalny", „z pasją" — te słowa nic nie znaczą,
bo używa ich każdy.

Marka mówi czasem dosadnie — nazwy kubków to część tożsamości, nie wpadka. Nie łagodź ich
i nie cenzuruj przy przepisywaniu tekstów.

## Polszczyzna — rzeczy, które się psują

- **Odmieniaj.** „Dodaj produktu" to sklejka szablonu. Jeśli zdanie ma zmienną, zbuduj je tak,
  żeby odmiana nie była potrzebna: „Usunąć: Miska z paprocią?" zamiast „Czy usunąć {nazwa}?".
- **Liczebniki:** 1 produkt · 2, 3, 4 produkty · 5+ produktów. Przy liczniku koszyka użyj formy
  bezpiecznej („sztuk: 5") albo obsłuż trzy przypadki. Nigdy „5 produkt".
- **Cena:** spacja przed „zł", przecinek dziesiętny — `349 zł`, `79,50 zł`. Zawsze przez funkcję
  `zl()`, nigdy sklejane ręcznie.
- **Ogonki wszędzie**, także w etykietach i komunikatach. Brak polskich znaków wygląda na tani skrypt.
- **Cudzysłów polski** „taki", nie "taki" — w tekstach widocznych dla klienta.
- **Wielka litera tylko na początku zdania.** Nie „Dodaj Produkt Do Koszyka".

## Słowa, których tu nie używamy

| Nie | Bo |
| --- | --- |
| dedykowany, kompleksowy, innowacyjny | język ofertowy, nikt tak nie mówi |
| produkt (do klienta) | to jest miska, kubek, talerz — nazwij rzecz |
| użytkownik | to jest osoba, która kupuje |
| system, platforma | to jest strona albo panel |
| kliknij tutaj | link mówi, dokąd prowadzi |
| z pasją, wyjątkowy, niepowtarzalny | puste, każdy tak pisze |
| Państwo | ta marka mówi na „Ty" |

## Długości

| Element | Limit |
| --- | --- |
| Etykieta przycisku | 4 słowa |
| Nazwa zakładki | 3 słowa |
| Pigułka z potwierdzeniem | 1 zdanie, do 60 znaków |
| Podpowiedź pod polem | 1 linia |
| Opis produktu | 2–4 zdania |
| Tytuł strony dla Google | do 60 znaków |
| Opis strony dla Google | do 155 znaków |
