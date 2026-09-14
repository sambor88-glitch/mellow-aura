---
name: mellowaura-druk
description: Materiały do druku i do ręki klienta — certyfikat unikatu, voucher na warsztat, metka, wizytówka, wkładka do paczki, plakat, naklejka. Format doc-page, wymiary w milimetrach, pismo w punktach, spady, kolory bezpieczne w druku i pliki dla drukarni. Użyj przy prośbie o „coś do wydrukowania", „karta do paczki", „voucher", „metka", „ulotka", „wizytówka", „PDF do drukarni".
---

# Materiały drukowane

Papier jest częścią produktu — klient rozpakowuje paczkę i pierwsze, co bierze do ręki po ceramice,
to karta z certyfikatem. Ma być z tej samej pracowni co kubek.

## Szkielet pliku

Materiał drukowany to `.dc.html`, ale inny niż strona: wymaga `doc-page.js` i pracuje w milimetrach.

```html
<x-dc>
<helmet>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,300;0,6..72,400;1,6..72,300&family=Instrument+Sans:wght@400;500&display=swap" rel="stylesheet" />
  <script src="./doc-page.js"></script>
  <style>
    doc-page:not(:defined) { visibility: hidden; }
    body { font-family: "Instrument Sans", system-ui, sans-serif; color: #2F2620; }
  </style>
</helmet>

<doc-page width="105mm" height="148mm">
  <section class="page" style="background: #F3EDE4; padding: 13mm 12mm; display: flex; flex-direction: column">
    <!-- treść -->
  </section>
</doc-page>
</x-dc>
```

Reguła `doc-page:not(:defined) { visibility: hidden; }` zapobiega mignięciu nieułożonej treści,
zanim `doc-page.js` się wczyta. Nie usuwaj jej.

Plik musi leżeć w tym samym katalogu co `support.js` **i** `doc-page.js`.

## Formaty

| Zapis | Co to | Użycie w projekcie |
| --- | --- | --- |
| `width="105mm" height="148mm"` | A6 pionowo | certyfikat unikatu, dwie strony |
| `orientation="landscape" size="a4"` | A4 poziomo | voucher warsztatowy |
| `margin="0.7in"` | dokument wielostronicowy | specyfikacja wdrożenia |

Druga strona to po prostu drugi `<section class="page">` w tym samym `doc-page`.

Inne przydatne formaty: wizytówka `90mm × 50mm`, metka do produktu `45mm × 90mm`,
wkładka do paczki `100mm × 150mm`, plakat A3 `297mm × 420mm`.

## Jednostki — nie mieszaj z ekranem

- **Wymiary i odstępy w `mm`.** `padding: 13mm 12mm`, `gap: 4.5mm`.
- **Pismo w `pt`.** Nazwa marki `15pt`, nagłówek kursywą `17pt`, treść `8pt`, nadtytuł `6.5pt`,
  drobny druk `6pt`. Poniżej `6pt` przestaje być czytelne na papierze niepowlekanym.
- **Linie w `mm`:** `0.4mm` linia rozdzielająca, `0.25mm dotted` linia do wpisania ręką.
- Żadnych `px`, `vw`, `clamp()`, `rem`. Kartka nie ma szerokości okna.

## Kolory na papierze

Paleta ta sama co na stronie, ale trzy uwagi:

- `#F3EDE4` jako tło zadrukowanej kartki wychodzi z drukarni ciemniej niż na ekranie.
  Przy nakładzie zamów odbitkę próbną, zanim puścisz tysiąc sztuk.
- Tło pełnokolorowe wymaga **spadu 3 mm** z każdej strony i przesunięcia treści o `3mm` do środka.
  Bez spadu po przycięciu zostaje biały pasek.
- Złoto `#A8813F` na ekranie to nie jest złoto w druku. Prawdziwy metaliczny efekt daje
  hot-stamping albo Pantone 871 — to osobna pozycja w wycenie, uprzedź o tym.
- Do druku cyfrowego zostaw RGB. Konwersję na CMYK robi drukarnia i zrobi ją lepiej.

## Typografia na papierze

- Newsreader `font-weight: 300` w nagłówkach, tak samo jak na stronie.
- Nazwa marki zawsze wersalikami z rozstrzeleniem `letter-spacing: 0.34em` i podpisem
  `ceramika · rękodzieło · kraków` w `6pt` pod spodem.
- Interlinia treści `1.3` — na papierze ciaśniejsza niż na ekranie, bo nie ma odblasku.
- Kursywa Newsreadera niesie zdanie, które ma zostać zapamiętane: *„Ta rzecz jest jedna
  i nie powtórzę jej nawet ja."* Jedno takie zdanie na materiał, nie trzy.

## Miejsca do wypełnienia ręką

Certyfikat podpisuje się długopisem, więc linie muszą mieć gdzie pomieścić pismo:

```html
<div style="display: flex; justify-content: space-between; align-items: baseline; gap: 4mm; border-bottom: 0.25mm dotted #C6B8A5; padding-bottom: 1.6mm">
  <span style="color: #8A7A69; letter-spacing: 0.08em">Praca</span><span style="flex: 1"></span>
</div>
```

Odstęp między liniami minimum `4.5mm`, inaczej pismo odręczne nachodzi na siebie.

## Zanim wyślesz do drukarni

1. Otwórz plik w przeglądarce i wydrukuj do PDF — **skala 100%, nie „dopasuj do strony"**.
2. Zmierz w PDF: kartka A6 ma mieć dokładnie 105 × 148 mm.
3. Sprawdź, czy czcionki z Google Fonts się wczytały — bez internetu wyjdzie font zastępczy
   i całość zmieni proporcje.
4. Nic ważnego bliżej niż `8mm` od krawędzi cięcia.
5. Wydrukuj jedną sztukę na zwykłej drukarce i weź do ręki. Na ekranie wszystko jest czytelne.
6. Zdjęcia w materiale: minimum 300 dpi w docelowym rozmiarze. Zdjęcie z katalogu `zdjecia/`
   ma około 1200 px — wystarczy do 10 cm szerokości, nie więcej.

## Co warto mieć wydrukowane

Poza certyfikatem i voucherem, które już są:

- **Metka przy produkcie** — nazwa, rozmiar, jak myć, kod QR do karty produktu.
- **Wkładka do paczki** — podziękowanie pierwszą osobą, prośba o zdjęcie z oznaczeniem, rabat na kolejne zakupy.
- **Wizytówka na targi** — z kodem QR do `/sklep`, bez adresu domowego pracowni.
- **Kartka z życzeniami** do zamówień oznaczonych jako prezent — wolne miejsce na tekst od kupującego.
