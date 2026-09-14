---
name: mellowaura-design
description: System wizualny MellowAura — paleta, typografia, gotowe komponenty i zasady kompozycji wyciągnięte z MellowAura.dc.html. Użyj ZAWSZE, zanim narysujesz albo zmienisz cokolwiek widocznego: nową sekcję, ekran, przycisk, kartę, formularz, materiał do druku, grafikę na social media. Także wtedy, gdy ktoś prosi o „ładniej", „spójnie z resztą", „w stylu strony" albo o kolory i czcionki marki.
---

# System wizualny MellowAura

Ten dokument jest źródłem prawdy. Wartości poniżej są policzone z `MellowAura.dc.html`, nie wymyślone.
Nie wprowadzaj nowego koloru, kroju ani promienia, jeśli nie ma go na tych listach — jeden obcy odcień
psuje wrażenie, że wszystko wyszło z jednej pracowni.

## Skąd bierze się ten styl

Ceramika formowana w dłoniach i jedwab z drugiego obiegu. Stąd wynikają trzy decyzje, które trzymają całość:

1. **Papier, nie ekran.** Tła są ciepłe i piaskowe, nigdy białe. Czysta biel (`#fff`) występuje wyłącznie
   w polach formularzy, żeby miejsce do wpisania było widoczne.
2. **Cienka szeryfowa kursywa jako głos autorki.** Newsreader w grubości 300 mówi, Instrument Sans informuje.
3. **Nic nie krzyczy.** Zero cieni rzuconych, zero gradientów, zero grubych ramek. Hierarchię robi
   odstęp i wielkość, nie kolor.

## Paleta

Nazwy tokenów są opisowe — używaj ich w komentarzach i rozmowie, w kodzie wpisujesz sam hex.

### Tła — od najciemniejszego do najjaśniejszego
| Hex | Rola | Gdzie |
| --- | --- | --- |
| `#2F2620` | atrament | tło przycisku głównego, stopka, cały tekst podstawowy |
| `#EDE4D8` | piasek ciemniejszy | tło panelu właścicielki, hover przycisku obrysowanego |
| `#F3EDE4` | piasek — **tło strony** | `body`, sekcje pełnej szerokości |
| `#F7F2EA` | len | tło wewnątrz kart, pasków pomocniczych, kolor tekstu na `#2F2620` |
| `#FCF9F4` | krem — **powierzchnia karty** | każda karta, która ma odstawać od tła |
| `#fff` | biel | wyłącznie `input`, `textarea`, `select` |

### Tekst — cztery poziomy, nie więcej
| Hex | Rola |
| --- | --- |
| `#2F2620` | nagłówki i treść główna |
| `#5C5043` | akapity prowadzące, lead |
| `#6B5D4F` | opisy pomocnicze |
| `#8A7A69` | etykiety wersalikowe, podpisy, metadane — **w nowym kodzie `#726456`**, patrz sekcja o dostępności |
| `#A0907D` | podpowiedzi pod polami — **w nowym kodzie `#736454`**, patrz sekcja o dostępności |

### Linie i obrysy
`#DCD0BE` obrys karty i pola · `#E2D7C7` linia rozdzielająca sekcje · `#C6B8A5` obrys przycisku
drugoplanowego i linia przerywana · `#E6DCCD` obrys najlżejszy, wewnątrz kart

### Akcenty — używaj oszczędnie
| Hex | Rola | Zasada |
| --- | --- | --- |
| `#8C6440` | brąz — linki, nadtytuły wersalikowe, kursywa w nagłówku | najczęstszy akcent — **w nowym kodzie `#855F3D`** |
| `#24417E` | granat — **każdy hover i focus** | nigdy jako tło dużej płaszczyzny |
| `#D6A39C` | róż — licznik koszyka, zaznaczenie tekstu, znaczniki | maksymalnie dwa na ekran |
| `#A8813F` | złoto — gwiazdki ocen, detal luksusowy | tylko drobnica |
| `#B98E64` | kreska 34×1 px przed nadtytułem | wyłącznie ten jeden element |
| `#8C3A2E` | czerwień — błąd, usuwanie, wyprzedane | nigdy dekoracyjnie |
| `#5C7A4A` | zieleń — potwierdzenie | nigdy dekoracyjnie |

## Typografia

Dwa kroje z Google Fonts, wczytywane w `<helmet>`:

```
Newsreader: 300, 400, 500 + kursywa 300, 400   — nagłówki, liczby, cytaty
Instrument Sans: 400, 500, 600                  — interfejs, akapity, przyciski
```

Zasady, od których nie ma odstępstw:

- **Nagłówki zawsze `font-weight: 300`.** Cienki szeryf to podpis marki. Pogrubiony Newsreader wygląda
  jak inna firma.
- **H1 skaluje się płynnie:** `font-size: clamp(42px, 6.2vw, 84px)`, `line-height: 0.98`,
  `letter-spacing: -0.02em`. Nagłówek sekcji: `clamp(32px, 4.4vw, 52px)`, `line-height: 1.04`.
- **Kursywa w brązie podkreśla jedno słowo w nagłówku**, nie całe zdanie:
  `<em style="font-style: italic; color: #8C6440">jednej pary</em>`.
- **Nadtytuł (eyebrow)** — to sygnatura tej strony, powtarza się kilkadziesiąt razy:
  `font-size: 10.5px; letter-spacing: 0.3em; text-transform: uppercase; color: #8C6440`.
- **Etykieta w karcie:** `font-size: 11.5px; letter-spacing: 0.14em; text-transform: uppercase; color: #8A7A69`.
- **Akapit prowadzący:** `17.5px / 1.68`, kolor `#5C5043`, szerokość `max-width: 46ch`.
  Tekst zwykły `14–15px / 1.6`. Nigdy nie puszczaj akapitu na całą szerokość 1280 px.
- **`text-wrap: pretty`** na każdym nagłówku i akapicie prowadzącym — nie zostawia sierot.
- Liczby i ceny eksponowane składaj Newsreaderem (`font-size: 30px; line-height: 1`), pod spodem
  etykieta wersalikowa. To wzorzec kafelka statystyki.

## Kształt

| Promień | Zastosowanie |
| --- | --- |
| `999px` | **wszystkie przyciski i znaczniki** — bez wyjątku, to najmocniejszy sygnał marki |
| `4px` | karty, pola formularza, kontenery |
| `6px` | kafelki ze zdjęciem |
| `3px` | miniatura wewnątrz karty, obrys focusu |

Zero `box-shadow`. Głębię buduje obrys `1px solid #DCD0BE` i zmiana tła o jeden stopień.

## Gotowe komponenty

Kopiuj i podmieniaj treść. Wszystkie hovery przez atrybut `style-hover` (format `.dc.html`).

**Przycisk główny**
```html
<button onClick="{{ akcja }}" style="background: #2F2620; color: #F7F2EA; border: 0; border-radius: 999px; padding: 16px 32px; font-size: 14px; letter-spacing: 0.04em" style-hover="background: #24417E">Treść</button>
```

**Przycisk drugoplanowy**
```html
<button onClick="{{ akcja }}" style="background: transparent; color: #2F2620; border: 1px solid #C6B8A5; border-radius: 999px; padding: 16px 32px; font-size: 14px; letter-spacing: 0.04em" style-hover="border-color: #2F2620; background: #EDE4D8">Treść</button>
```

**Karta**
```html
<div style="background: #FCF9F4; border: 1px solid #DCD0BE; border-radius: 4px; padding: 28px 26px">
  <div style="font-family: Newsreader, serif; font-size: 25px; margin-bottom: 4px">Tytuł</div>
  <p style="font-size: 13.5px; color: #8A7A69; margin: 0 0 22px">Zdanie wyjaśniające.</p>
</div>
```

**Pole formularza** — biel, żeby było widać, gdzie się pisze
```html
<input value="{{ wartosc }}" onChange="{{ onZmiana }}" placeholder="Podpowiedź" style="width: 100%; min-width: 0; background: #fff; border: 1px solid #DCD0BE; border-radius: 4px; padding: 14px 16px; font-size: 15px" style-focus="border-color: #2F2620; outline: none" />
```

**Nadtytuł z kreską**
```html
<div style="display: flex; align-items: center; gap: 12px; margin-bottom: 26px">
  <span style="width: 34px; height: 1px; background: #B98E64"></span>
  <span style="font-size: 10.5px; letter-spacing: 0.3em; text-transform: uppercase; color: #8C6440">home studio &middot; kraków</span>
</div>
```

**Znacznik (chip)**
```html
<span style="border-radius: 999px; padding: 5px 12px; font-size: 11.5px; letter-spacing: 0.06em; background: #D6A39C; color: #2F2620">nowość</span>
```

## Kompozycja

- Kontener treści: `max-width: 1280px; margin: 0 auto; padding: 0 28px`. Ta sama wartość wszędzie.
- Odstęp pionowy sekcji: `72px` u góry, `84px` na dole. Sekcja gęstsza: `44px / 96px`.
- Kolumny przez `flex-wrap: wrap` z `flex: 1 1 400px` — same się układają jedna pod drugą na telefonie.
  Nie pisz `@media` do zmiany układu, jeśli wystarczy `flex-wrap`.
- Każdy element w kolumnie elastycznej dostaje `min-width: 0`, inaczej długi wyraz rozpycha stronę w bok.
- Rytm: nadtytuł → nagłówek → akapit prowadzący → przyciski → pasek liczb. W tej kolejności.

## Ruch

Cztery animacje, zdefiniowane raz w `<helmet>`, nie dokładaj piątej:

| Nazwa | Do czego |
| --- | --- |
| `maIn` | wejście widoku: `animation: maIn .4s ease both` |
| `maUp` | wjazd treści od dołu o 14 px |
| `maSlide` | panel boczny, koszyk |
| `maPulse` | element czekający na dane |

Blok `@media (prefers-reduced-motion: reduce)` już jest — nie usuwaj go.

## Dostępność — to nie jest opcja

- Focus: `outline: 2px solid #24417E; outline-offset: 2px`. Nie kasuj go „dla estetyki".
- Kontrast — wartości policzone, nie na oko: `#2F2620` na `#F3EDE4` daje 12,72:1.
  Ale **`#8A7A69` daje tylko 3,29–3,95, a `#A0907D` 2,46–2,95** i przy piśmie poniżej 24 px
  żaden z nich nie spełnia normy 4,5:1. W nowych ekranach używaj zamienników:
  **`#8A7A69` → `#726456`**, **`#A0907D` → `#736454`**, **`#8C6440` → `#855F3D`**
  (ten sam ton, tylko ciemniejszy; kontrast co najmniej 4,51 w najgorszym przypadku).
  Każdy nowy kolor tekstu przepuść przez `kontrast.py` — szczegóły w skillu `mellowaura-dostepnosc`.
- Każde `<img>` ma `alt` po polsku, opisujące przedmiot, nie plik.
- Pole obok pola ma etykietę tekstową, nie sam `placeholder`.

## Czego nie robić

| Nie | Dlaczego |
| --- | --- |
| Białe tło `#fff` na płaszczyźnie | zimne, wypada ze świata ceramiki |
| `box-shadow`, gradient, glassmorphism | ten projekt buduje głębię obrysem |
| Pogrubiony nagłówek szeryfowy | traci charakter pisma odręcznego |
| Nowy odcień „bo pasuje" | paleta ma 20 pozycji, w niej jest odpowiedź |
| Przycisk z rogiem innym niż `999px` | rozpada się spójność |
| Ikony z zewnętrznej biblioteki | strona nie używa żadnej, znaki `&middot;` i `×` wystarczają |

## Dokąd dalej

Ten skill mówi, **jak to ma wyglądać**. O tym, czy działa i czy klient dojdzie do końca,
mówią trzy pozostałe: `mellowaura-ux` (ścieżki i formularze), `mellowaura-teksty` (co jest napisane
na przycisku) i `mellowaura-dostepnosc` (kontrast, klawiatura, czytniki ekranu).
