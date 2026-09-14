---
name: mellowaura-ux
description: Zachowanie interfejsu i ścieżki użytkownika — ścieżka zakupu, koszyk, formularze, walidacja, stany przycisków, potwierdzenia, błędy, ładowanie, sygnały zaufania, momenty decyzji na karcie produktu. Użyj, gdy pytanie dotyczy tego, czy coś jest wygodne, zrozumiałe i czy klient dokończy zakup: „UX", „przepływ", „ścieżka", „porzucone koszyki", „ludzie nie kupują", „za dużo klikania", „czy to jest jasne", „gdzie się gubią".
---

# UX MellowAury

Skill `mellowaura-design` pilnuje, **jak to wygląda**. Ten pilnuje, **czy ktoś dokończy**.
Te dwie rzeczy rozjeżdżają się częściej, niż się wydaje: ładny ekran potrafi gubić klientów,
a brzydki potrafi sprzedawać.

## Kto tu przychodzi

Jedna osoba, z telefonu, z Instagrama, zwykle wieczorem, często w trakcie robienia czegoś innego.
Kupuje rzecz za 79–399 zł, której nie może wziąć do ręki, od kogoś, kogo nie zna.
Nie zakłada konta, nie czyta regulaminu i nie wróci jutro, żeby dokończyć.

Z tego wynika wszystko poniżej.

## Ścieżka zakupu — wybrany wariant i dlaczego

W repozytorium leżą dwa warianty: `Zakup A - krok po kroku` i `Zakup B - ekspres BLIK`.
**Wybrany został B i jest wbudowany w stronę.** Jego obietnica to „Zapłacone w 14 sekund":
jeden ekran, trzy pola, kod BLIK, koniec. Resztę danych dobiera InPost z numeru telefonu.

Zasada nadrzędna: **każde dodatkowe pole i każdy dodatkowy ekran kosztuje klientów.**
Zanim dołożysz cokolwiek do ścieżki zakupu, odpowiedz, czy paczka bez tego nie dojdzie.
Jeśli dojdzie — to pole nie należy do ścieżki zakupu.

Rzeczy rzadkie chowaj za rozwinięciem, nie wyrzucaj. Wzorzec `showMore` / `toggleMore` już to robi:

```
Inny adres, faktura na firmę, dopisek do paczki  +
```

Domyślnie zwinięte. Kto potrzebuje, klika. Reszta nawet tego nie zauważa.

## Przycisk, który nigdy nie jest wyłączony

To najlepsza decyzja UX w tym projekcie i trzymaj się jej wszędzie.
Wyłączony przycisk nie mówi, czego brakuje — człowiek klika, nic się nie dzieje i wychodzi.

Tutaj przycisk płatności **zmienia treść i kolor, ale zostaje klikalny**:

```js
payReady:    s.pay !== 'blik' || s.blik.length === 6,
payBigLabel: payReady ? 'Płacę ' + zl(sub + ship) : 'Wpisz kod BLIK, żeby zapłacić',
payBigBg:    payReady ? '#24417E' : '#A89684',
```

A kliknięcie mimo braku danych daje odpowiedź, nie ciszę:

```js
if (pay === 'blik' && blik.replace(/\D/g, '').length !== 6) {
  this.toast('Wpisz 6-cyfrowy kod z aplikacji banku'); return;
}
```

Przenieś ten wzorzec na każdy formularz, który dodajesz: **etykieta przycisku mówi albo co się stanie
(„Płacę 349 zł"), albo czego brakuje („Wpisz kod BLIK, żeby zapłacić"). Nigdy samo „Dalej".**

## Walidacja, która prowadzi zamiast karcić

W tym projekcie pole nie robi się czerwone. Prowadzi do przodu:

```js
blikBorder: s.blik.length === 6 ? '#24417E' : '#C9D4EA',
blikHint:   s.blik.length === 6 ? 'Kod gotowy — potwierdzisz w aplikacji' : '6 cyfr, ważne 2 minuty',
```

Trzy reguły, które z tego wynikają:

1. **Czyść dane u źródła, nie po fakcie.** `e.target.value.replace(/\D/g, '').slice(0, 6)` —
   w pole na kod BLIK po prostu nie da się wpisać litery. Nie ma czego walidować, więc nie ma błędu.
2. **Podpowiedź jest zanim człowiek zacznie pisać**, nie po tym, jak się pomyli.
   „6 cyfr, ważne 2 minuty" stoi tam od początku.
3. **Czerwień `#8C3A2E` dopiero po próbie wysłania**, nigdy w trakcie pisania. Człowiek, który
   wpisał trzy cyfry z sześciu, nie popełnił błędu — jest w połowie.

## Momenty decyzji na karcie produktu

Klient przy ceramice pyta o cztery rzeczy w tej kolejności. Odpowiedz w tej samej:

| Pytanie | Czym odpowiadasz |
| --- | --- |
| Jak to naprawdę wygląda? | zdjęcie w prawdziwym kolorze + detal + powiększenie |
| Jak duże to jest? | wymiary w cm i zdjęcie obok czegoś znanego |
| Czy dojdzie całe i kiedy? | termin wysyłki i zdanie o pakowaniu |
| Czy to jest tego warte? | zdanie o robocie ręcznej, certyfikat unikatu |

Cena i przycisk „Do koszyka" nie mogą wyjechać poza ekran przy czytaniu opisu — na telefonie
zostają przypięte na dole (`mellowaura-aplikacja` ma gotowy fragment).

## Koszyk

- **Dodanie do koszyka nie porywa klienta ze strony.** Zostaje tam, gdzie był, dostaje pigułkę
  z potwierdzeniem i licznik w nagłówku rośnie. Przeniesienie na osobną stronę koszyka
  po każdym dodaniu kończy zakupy na jednej rzeczy.
- **Pasek do darmowej wysyłki** (`freePct`, `freeLeftLabel`) pokazuje, ile brakuje.
  Napisz kwotę, która brakuje, nie procent — „jeszcze 51 zł do darmowej wysyłki".
- **Pusty koszyk ma wyjście.** Nie „Koszyk jest pusty", tylko zdanie i przycisk prowadzący do sklepu.
- Szuflada zamyka się na trzy sposoby: krzyżyk, kliknięcie w tło, Esc. Wszystkie trzy muszą działać.

## Po zakupie

Ekran potwierdzenia to nie koniec, tylko początek relacji. Musi zawierać:

1. **Numer zamówienia** w formacie `MA-2026-1047` — duży, do skopiowania.
2. **Co się teraz stanie i kiedy** — „Paczka wychodzi w środę, dostaniesz numer do śledzenia".
3. **Gdzie szukać maila** — razem z prośbą o sprawdzenie folderu ze spamem.
4. **Jedno wyjście dalej** — wróć do sklepu albo zobacz warsztaty. Nie ślepy zaułek.

## Zaufanie

Klient kupuje od nieznajomej osoby przez internet. Cztery rzeczy, które to odblokowują,
i wszystkie już są w projekcie — nie usuwaj ich przy przebudowach:

- **Twarz i imię.** Sekcja „O mnie" pisana pierwszą osobą to nie ozdoba, to argument sprzedażowy.
- **Zdjęcia pracowni**, nie tylko produktu wyciętego na tle.
- **Konkret zamiast obietnicy.** „Wysyłka w 3–5 dni" znaczy więcej niż „szybka wysyłka".
- **Zdanie o danych** przy płatności: „Bez zakładania konta. Dane zapisuję tylko na czas realizacji."

## Czekanie

Nic w interfejsie nie może wyglądać na zawieszone:

- Tło `#E6DCCD` pod każdym zdjęciem, zanim się wczyta — piaskowy prostokąt, nie biała dziura.
- Akcja dłuższa niż pół sekundy zmienia etykietę przycisku: „Płacę…" i blokada podwójnego kliknięcia.
- Animacja `maPulse` dla elementu, który czeka na dane.
- Nigdy kręciołek na środku pustego ekranu bez podpisu.

## Błąd płatności

Najdroższy moment w całym sklepie — klient chciał zapłacić i nie wyszło. Co musi się stać:

- **Koszyk zostaje nietknięty.** Nigdy nie czyść koszyka po nieudanej płatności.
- Komunikat mówi, co dalej, nie co się stało: „Bank odrzucił kod. Spróbuj jeszcze raz
  albo zapłać przelewem" — z przyciskiem do obu opcji.
- Żadnych kodów technicznych na ekranie. Kod błędu idzie do panelu, nie do klienta.
- Alternatywna metoda płatności widoczna od razu, bez wracania o krok.

## Lista kontrolna nowego ekranu

1. Da się przejść do końca jednym kciukiem, bez powiększania?
2. Każdy przycisk mówi, co się stanie po kliknięciu?
3. Każdy stan pusty ma zdanie i wyjście?
4. Każdy błąd mówi, co zrobić dalej?
5. Da się cofnąć bez utraty tego, co już wpisane?
6. Czy któreś pole da się usunąć albo schować pod „+"?
7. Czy po zamknięciu i powrocie człowiek wraca tam, gdzie był?
