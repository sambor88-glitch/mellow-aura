---
name: mellowaura-aplikacja
description: Ekrany dotykowe i zachowanie na telefonie — układ płynny bez punktów łamania, wysokość celów dotykowych, przypięty przycisk zakupu, szuflada koszyka, warstwy z-index, powiększanie zdjęć, klawiatura ekranowa, aplikacja na ekranie głównego telefonu (PWA). Użyj przy prośbie o „żeby dobrze działało na telefonie", „aplikacja", „ekran mobilny", „wersja na komórkę", „żeby się dało zainstalować".
---

# Telefon i ekrany dotykowe

Większość osób wchodzi na MellowAurę z Instagrama, czyli z telefonu trzymanego w jednej ręce,
często w ruchu. Ekran telefonu jest wersją podstawową, komputer to wariant rozszerzony — nie odwrotnie.

## Ten projekt nie ma punktów łamania — i tak ma zostać

W całym `MellowAura.dc.html` jest **jedno** zapytanie medialne: `prefers-reduced-motion`.
Cała reszta układa się płynnie. Zanim napiszesz `@media`, sprawdź, czy nie wystarczy jedno z trzech:

| Narzędzie | Zapis | Efekt |
| --- | --- | --- |
| Zawijanie kolumn | `display: flex; flex-wrap: wrap; gap: 26px` + `flex: 1 1 340px` | kolumny same wchodzą jedna pod drugą |
| Siatka samoskalująca | `grid-template-columns: repeat(auto-fill, minmax(260px, 1fr))` | liczba kolumn dopasowana do szerokości |
| Płynny rozmiar pisma | `clamp(32px, 4.4vw, 52px)` | nagłówek rośnie razem z ekranem |

Do tego **`min-width: 0` na każdym elemencie w kolumnie elastycznej**. Bez tego długa nazwa produktu
rozpycha stronę i pojawia się poziome przewijanie — najczęstsza usterka mobilna w tym projekcie.

Zapytanie medialne piszesz dopiero wtedy, gdy zmienia się kolejność albo znika cały element.

## Cele dotykowe

- Minimum **44 × 44 px** klikalnego obszaru. Przyciski w projekcie mają `padding: 16px 32px` — mieszczą się z zapasem.
- Drobne akcje (`×`, strzałki galerii) dostają `padding: 8px 12px` wokół znaku, nawet jeśli sam znak jest mały.
- Odstęp między sąsiednimi celami co najmniej `10px`. Dwa przyciski stykające się krawędziami to pomyłkowe kliknięcia.
- Nic klikalnego bliżej niż `16px` od krawędzi ekranu.

## Pola formularza

- **`font-size: 15px` to minimum.** Poniżej 16 px Safari na iPhonie sam przybliża stronę przy kliknięciu
  w pole i użytkownik ląduje w przypadkowym powiększeniu. W projekcie pola mają `15px` — nie zmniejszaj.
- `type` steruje klawiaturą: `type="tel"` do telefonu i kodu BLIK, `type="email"` do adresu,
  `inputmode="numeric"` do liczby sztuk.
- `autocomplete` skraca płacenie o minutę: `name`, `email`, `tel`, `street-address`, `postal-code`.
- Etykieta nad polem, nie tylko `placeholder` — po wpisaniu treści placeholder znika razem z informacją, co to za pole.

## Przypięty przycisk zakupu

Na karcie produktu cena i „Do koszyka" nie mogą wyjechać poza ekran przy przewijaniu opisu:

```html
<div style="position: sticky; bottom: 0; z-index: 50; background: rgba(243, 237, 228, 0.96); backdrop-filter: blur(14px); border-top: 1px solid #E2D7C7; padding: 14px 16px calc(14px + env(safe-area-inset-bottom)); display: flex; gap: 12px; align-items: center">
  <div style="flex: 1 1 auto; min-width: 0">
    <div style="font-family: Newsreader, serif; font-size: 22px; line-height: 1">{{ cena }}</div>
    <div style="font-size: 11.5px; color: #8A7A69">{{ wariant }}</div>
  </div>
  <button onClick="{{ doKoszyka }}" style="flex: 0 0 auto; background: #2F2620; color: #F7F2EA; border: 0; border-radius: 999px; padding: 15px 28px; font-size: 14px" style-hover="background: #24417E">Do koszyka</button>
</div>
```

`env(safe-area-inset-bottom)` odsuwa przycisk od paska gestów na iPhonie. Bez tego palec trafia w pasek systemowy.

## Warstwy — trzymaj tę skalę

| z-index | Element |
| --- | --- |
| 50 | pasek przypięty na dole |
| 60 | nagłówek przyklejony na górze |
| 90 | szuflada koszyka |
| 110 | powiększone zdjęcie |
| 120 | pigułka z potwierdzeniem |

Nie wymyślaj `z-index: 9999`. Jeśli coś się chowa, znaczy, że jest w złej warstwie, nie że brakuje liczby.

## Szuflada koszyka

Wjeżdża z prawej przez `animation: maSlide`, przyciemnienie tła `rgba(31, 25, 20, 0.88)`.
Zamyka się na trzy sposoby i wszystkie trzy muszą działać: krzyżyk, kliknięcie w tło, klawisz Esc.
Przy otwartej szufladzie strona pod spodem nie może się przewijać.

## Zdjęcia

- Kafelek produktu: `aspect-ratio: 4/5`, `object-fit: cover`, tło `#E6DCCD` widoczne zanim zdjęcie się wczyta.
- Zdjęcia poniżej pierwszego ekranu dostają `loading="lazy"`.
- Powiększenie: pełny ekran, `cursor: zoom-out`, wyjście kliknięciem gdziekolwiek i klawiszem Esc.
- Galeria przewijana palcem: `overflow-x: auto; scroll-snap-type: x mandatory`, każdy kafelek
  `scroll-snap-align: start`. Pasek przewijania ukryty, ale gest działa.

## Aplikacja na ekranie głównym

Gdy pada pytanie „czy to może być aplikacja" — najtańsza dobra odpowiedź to PWA: ta sama strona,
dodana do ekranu głównego, otwiera się bez paska przeglądarki. Potrzebne są trzy rzeczy:

1. `manifest.json` — nazwa `MellowAura`, `display: "standalone"`, `background_color: "#F3EDE4"`,
   `theme_color: "#2F2620"`, ikony 192 i 512 px.
2. `<meta name="theme-color" content="#F3EDE4">` — pasek systemowy dostaje kolor piasku.
3. Service worker z pamięcią podręczną na zdjęcia produktów.

Czego PWA nie da: płatności w App Store, powiadomień push na iPhonie bez dodania do ekranu głównego,
obecności w sklepach z aplikacjami. Powiedz to wprost, zanim ktoś zapłaci za aplikację natywną,
której ten biznes na tym etapie nie potrzebuje.

## Sprawdzenie przed oddaniem

1. Szerokość 390 px (iPhone 14) — **nic nie przewija się w bok**.
2. Szerokość 360 px (starszy Android) — przyciski nadal mieszczą podpis w jednej linii.
3. Klawiatura ekranowa nie zasłania pola, które się właśnie wypełnia.
4. Ścieżka zakupu do końca jednym kciukiem, bez powiększania.
5. Zdjęcie produktu wczytuje się w mniej niż dwie sekundy przy wolnym łączu.
6. Obrót ekranu na bok niczego nie rozsypuje.
