# MellowAura

Sklep i strona pracowni ceramiki Kasi Samborskiej z Krakowa. W repozytorium jest na razie prototyp
i dokumentacja — kodu aplikacji jeszcze nie ma.

## Źródła prawdy

| Plik | Rozstrzyga |
| --- | --- |
| `MellowAura.dc.html` | wygląd, teksty i zachowanie każdego ekranu — przenoś, nie projektuj od nowa |
| `Specyfikacja wdrozenia - MellowAura.dc.html` | adresy, tytuły, opisy, JSON-LD, zakres panelu, integracje |
| `Plan wdrozenia - Laravel krok po kroku.dc.html` | stos, schemat bazy, harmonogram do 10 listopada |
| `.claude/skills/` | paleta, komponenty, UX, teksty, dostępność — kolory i kroje zmieniasz tylko w `mellowaura-design` |

Pliki `.dc.html` otwierają się w przeglądarce i muszą leżeć obok `support.js`
(dokumenty do druku także obok `doc-page.js`).

## Stos

Laravel 13 + Blade, Alpine.js, MySQL, własny panel na Blade (nie Filament), paczki Spatie: medialibrary,
sitemap, schema-org. Serwer na Forge, przed nim Cloudflare. PHP 8.4 lokalnie i na serwerze.
Bez Next.js, Astro i WooCommerce — uzasadnienie w planie, punkt 3.

## Zasady, których nie łamiemy

- Stan magazynu zdejmuj dopiero w webhooku potwierdzającym płatność, w transakcji z `lockForUpdate`.
- Webhook płatności: weryfikuj podpis i loguj każde wywołanie.
- Kwoty jako grosze w liczbach całkowitych albo `decimal(10,2)` — nigdy float.
- Pozycja zamówienia kopiuje cenę, napis i kolor z konfiguratora, a nie tylko klucze obce.
- `custom_text` (napis na kubku): limit długości po stronie serwera i escapowanie przy każdym wyświetleniu.
- Teksty w interfejsie po polsku, głosem Kasi (`mellowaura-teksty`); nazwy w kodzie i w bazie po angielsku.

## Repozytorium

- Publiczne: `sambor88-glitch/mellow-aura`, gałąź `main`, wypychanie przez SSH.
- Nigdy nie commituj `uploads/` (surowe zrzuty mogą zawierać dane klientek), `.env` ani kluczy — są w `.gitignore`.
- Projekt w Claude Design czyta z tego repozytorium (`github.md`). Zmianę w `.dc.html` wypchnij, a przed pracą
  w Claude Design pobierz aktualną wersję z repo, żeby się nie rozjechały.

## Komendy

Do uzupełnienia po założeniu projektu Laravela: serwer lokalny, testy, migracje.
