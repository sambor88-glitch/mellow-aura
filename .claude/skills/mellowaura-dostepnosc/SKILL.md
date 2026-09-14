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
| `#8C6440` brąz | **4,4977** | **4,16** | 4,98 | **brakuje 0,0023 do progu** |
| `#8A7A69` etykiety | **3,56** | **3,29** | **3,95** | **za mało dla tekstu zwykłego** |
| `#A0907D` podpowiedzi | **2,66** | **2,46** | **2,95** | **za mało dla czegokolwiek** |

Sprawdzenie dowolnej pary:

```bash
python3 .claude/skills/mellowaura-dostepnosc/kontrast.py '#8A7A69' '#F3EDE4'
python3 .claude/skills/mellowaura-dostepnosc/kontrast.py --fix '#A0907D'
```

## Co z tego wynika dla istniejącej strony

Trzy ustalone fakty, policzone na `MellowAura.dc.html`:

1. **`#8A7A69` występuje jako kolor tekstu 99 razy, z czego 88 przy piśmie mniejszym niż 14 px.**
   Przy 11,5 px to jest tekst zwykły, więc obowiązuje próg 4,5:1 — a jest 3,29–3,95.
   Etykiety wersalikowe, podpisy pod polami i metadane nie przechodzą.
2. **`#A0907D` występuje 46 razy** i nie przechodzi żadnego progu na żadnym jasnym tle.
   To kolor podpowiedzi pod polami formularza — czyli tekstu, który ma pomagać w płaceniu.
3. **`#8C6440` ma 4,4977 na tle strony — czyli o 0,0023 za mało.** Brąz nadtytułów i linków
   wygląda na przechodzący, a formalnie nie przechodzi; na tle panelu `#EDE4D8` to już 4,16.
   Występuje 31 razy, prawie zawsze przy piśmie 10,5 px. To jest ten rodzaj różnicy, którego
   nie da się zobaczyć okiem i dlatego istnieje `kontrast.py`.

Bezpieczne zamienniki, ten sam ton, tylko ciemniejsze — przechodzą AA na wszystkich czterech jasnych tłach:

| Zamiast | Użyj | Najgorszy przypadek |
| --- | --- | --- |
| `#8A7A69` | **`#726456`** | 4,54 na `#EDE4D8` |
| `#A0907D` | **`#736454`** | 4,54 na `#EDE4D8` |
| `#8C6440` | **`#855F3D`** | 4,51 na `#EDE4D8` |

**Podmiana w plikach produkcyjnych nie jest częścią tego pakietu** — to zmiana wyglądu w blisko
150 miejscach i powinna być osobną, świadomą decyzją. Ale **w każdym nowym ekranie od razu używaj
wartości poprawionych**, nie starych.

Wyjątek, w którym stare odcienie są w porządku: tekst od 24 px w górę (próg 3:1) oraz element
czysto dekoracyjny, którego nikt nie musi przeczytać.

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
