---
name: mellowaura-dostepnosc
description: Dostępność strony i panelu — kontrast policzony dla palety MellowAury, obsługa klawiaturą, czytniki ekranu, formularze, focus, ruch, cele dotykowe, wymogi prawne dla sklepu. Skrypt kontrast.py liczy każdą parę kolorów. Użyj przy pytaniu o dostępność, WCAG, kontrast, „czy to jest czytelne", „czy osoba niewidoma to obsłuży", „czy to jest zgodne z przepisami", a także przed dodaniem nowego koloru tekstu.
---

# Dostępność MellowAury

Dwie rzeczy naraz: część klientek to kobiety po pięćdziesiątce czytające drobny druk na telefonie
wieczorem, a od czerwca 2025 sklepy internetowe w Unii obejmuje European Accessibility Act.
To przestało być uprzejmością.

## Policzony kontrast palety

Wartości z `kontrast.py` w tym katalogu. Progi WCAG: **4,5:1** tekst zwykły, **3:1** tekst duży
(od 24 px, albo 19 px pogrubiony), **7:1** poziom AAA.

| Kolor tekstu | piasek `#F3EDE4` | panel `#EDE4D8` | karta `#FCF9F4` | Werdykt |
| --- | --- | --- | --- | --- |
| `#2F2620` atrament | 12,72 | 11,76 | 14,09 | AAA wszędzie |
| `#4A3E33` | 8,90 | 8,23 | 9,86 | AAA wszędzie |
| `#5C5043` lead | 6,72 | 6,22 | 7,45 | AA, na karcie AAA |
| `#6B5D4F` | 5,46 | 5,05 | 6,05 | AA |
| `#24417E` granat | 8,46 | 7,83 | 9,38 | AAA wszędzie |
| `#8C3A2E` czerwień | 6,54 | 6,05 | 7,25 | AA |
| `#855F3D` brąz | 4,87 | 4,51 | 5,40 | AA |
| `#726456` etykiety | 4,91 | 4,54 | 5,44 | AA |
| `#736454` podpowiedzi | 4,90 | 4,54 | 5,43 | AA |

Na ciemnym tle `#2F2620` obowiązuje odwrotna logika — tekst trzeba **rozjaśniać**.

| Kolor tekstu na `#2F2620` | Kontrast | Werdykt |
| --- | --- | --- |
| `#F3EDE4` piasek — logo | 12,72 | AAA |
| `#D8CDBD` tekst i linki w stopce | 9,43 | AAA |
| `#D6A39C` róż — hover linku | 6,75 | AA |
| `#B8AB99` akapit w stopce | 6,57 | AA |
| `#9B8C7C` etykiety | 4,54 | AA |

Nigdy nie przenoś tam odcienia z pierwszej tabeli: `#726456` daje na ciemnym tle 2,59.
Obrysy `#55483C` i `#453A30` w stopce to linie, nie tekst, więc progu 4,5 nie mają.

Sprawdzenie dowolnej pary:

```bash
python3 .claude/skills/mellowaura-dostepnosc/kontrast.py '#8A7A69' '#F3EDE4'
python3 .claude/skills/mellowaura-dostepnosc/kontrast.py --fix '#A0907D'
```

## Co zostało poprawione i co jeszcze zostaje

Strona przeszła audyt kontrastu. Liczba elementów tekstowych poniżej progu WCAG spadła
z **238 do 53**. Cztery kolory, które za to odpowiadały, zostały wymienione w całym projekcie:

| Było | Jest | Ile miejsc | Dlaczego nie przechodziło |
| --- | --- | ---: | --- |
| `#8A7A69` | `#726456`, na ciemnym tle `#9B8C7C` | 105 | 3,29–3,95 przy piśmie zwykle 11,5 px |
| `#A0907D` | `#736454` | 46 | 2,46–2,95 — za mało na każdym tle |
| `#8C6440` | `#855F3D` | 31 | 4,4977, czyli 0,0023 poniżej progu |
| `#9A8978` | `#726456` | 1 | 2,90 — podtytuł pod logo, 9 px (poprawiony 15.09.2026) |

Wiersz z brązem warto zapamiętać: różnicy 0,0023 nie da się zobaczyć okiem. Brąz wyglądał
na przechodzący i nie przechodził. Po to jest `kontrast.py`.

**Stare odcienie nie występują już nigdzie w projekcie.** Jeśli trafisz na `#8A7A69`, `#A0907D`,
`#8C6440` albo `#9A8978` w kodzie, to znaczy, że ktoś wkleił je z zewnątrz — popraw od razu.

### Pozostałe 53 pozycje — świadomie nietknięte

| Kolor | Pozycji | Co to jest |
| --- | ---: | --- |
| `#A8813F` | 13 | złoto — gwiazdki ocen, znaczniki, numery kroków |
| `#B0A190` | 5 | szarość krzyżyków „usuń" |
| pozostałe | 6 | `#9A8978` przy 9 px, ozdobne `✦`, `#E2D7C7` |

To osobne decyzje projektowe, nie ten sam defekt: złoto jest świadomym akcentem luksusowym,
a `✦` jest ozdobą bez treści. Jeśli któraś z nich ma przejść normę, policz zamiennik
skryptem i zmień świadomie — nie przy okazji innej pracy.

## Klawiatura

Cała ścieżka zakupu musi dać się przejść bez myszy — tak korzystają osoby niewidome
i osoby z drżeniem rąk.

- **Focus jest widoczny:** `outline: 2px solid #24417E; outline-offset: 2px`. Jest w `<helmet>`,
  nigdy go nie kasuj — `outline: none` bez zamiennika to najczęstszy błąd dostępności w sklepach.
- **Kolejność tabulatora zgodna z układem.** Nie używaj `tabindex` większego od zera.
- **Szuflada koszyka przejmuje focus** po otwarciu i oddaje go z powrotem na przycisk koszyka
  po zamknięciu. Tabulator nie może uciekać na stronę pod spodem.
- **Esc zamyka** szufladę, powiększone zdjęcie i każde okno nakładane.
- Element klikalny to `<button>` albo `<a>`. `<div onClick>` nie działa ze spacji ani Entera
  i czytnik go nie ogłasza.

## Czytnik ekranu

- Każde `<img>` ma `alt` po polsku opisujący przedmiot. Zdjęcie dekoracyjne: `alt=""`, nie brak atrybutu.
- Przycisk z samym znakiem (`×`, `+`) dostaje `aria-label="Zamknij"`, `aria-label="Dodaj rozmiar"`.
  Sam znak czytnik przeczyta jako „litera x".
- Jeden `<h1>` na widok, dalej `<h2>`, `<h3>` bez przeskoków. Nagłówki to spis treści dla czytnika.
- **Informacja nigdy nie siedzi wyłącznie w kolorze.** Status zamówienia ma nazwę słowną obok
  kolorowej pigułki. Zielona kropka bez podpisu nie znaczy nic dla osoby nierozróżniającej barw
  — a to około 8% mężczyzn.
- Licznik koszyka i pigułka z potwierdzeniem: `aria-live="polite"`, żeby czytnik ogłosił zmianę.

## Formularze

- Etykieta tekstowa nad polem, powiązana z nim. Sam `placeholder` znika po wpisaniu pierwszej litery
  i osoba przestaje wiedzieć, co wypełnia.
- `autocomplete` nie tylko przyspiesza — to wymóg WCAG 1.3.5: `name`, `email`, `tel`,
  `street-address`, `postal-code`.
- Błąd opisany słowem, nie samą czerwoną ramką, i powiązany z polem przez `aria-describedby`.
- `inputMode="numeric"` przy kodzie BLIK i numerze telefonu.
- Pole o rozmiarze pisma minimum 15 px — poniżej 16 px Safari na iPhonie samo przybliża ekran.

## Ruch

Blok `@media (prefers-reduced-motion: reduce)` jest w `<helmet>` i wyłącza wszystkie cztery
animacje. **Nie usuwaj go** — dla osób z zaburzeniami przedsionkowymi ruchomy interfejs
wywołuje realne mdłości.

Nic nie miga częściej niż trzy razy na sekundę.

## Cele dotykowe

Minimum 44 × 44 px klikalnego obszaru, minimum 10 px odstępu między sąsiednimi celami.
Szczegóły i pomiary w `mellowaura-aplikacja`.

## Powiększenie

Strona musi działać przy powiększeniu do 200% bez poziomego przewijania.
Ten projekt jest na to odporny dzięki układowi płynnemu (`flex-wrap`, `clamp()`),
pod warunkiem że każdy element w kolumnie elastycznej ma `min-width: 0`.

W `<meta name="viewport">` nigdy nie może pojawić się `user-scalable=no` ani `maximum-scale=1`.

## Szybkie sprawdzenie nowego ekranu

1. Odłóż mysz i przejdź ekran samym tabulatorem — widać, gdzie jesteś?
2. Powiększ przeglądarkę do 200% — nic nie wyjeżdża w bok?
3. Przepuść każdy nowy kolor tekstu przez `kontrast.py`.
4. Każde zdjęcie ma sensowny `alt`?
5. Każdy przycisk ze znakiem ma `aria-label`?
6. Czy którakolwiek informacja istnieje wyłącznie jako kolor?
7. Czy focus jest widoczny na każdym elemencie, także na ciemnym tle?
