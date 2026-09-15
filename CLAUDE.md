# MellowAura

Sklep i strona pracowni ceramiki Kasi Samborskiej z Krakowa. W repozytorium jest prototyp, dokumentacja
i szkielet aplikacji Laravel 13 (katalogi `app/`, `config/`, `public/` itd.). Serwer serwuje wyłącznie `public/`.

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

## Moduły

Kod jest modularny: każdy obszar sklepu to osobny moduł w `app/Modules/<Name>`, w przestrzeni nazw
`App\Modules\<Name>`. Bez paczek do modułów — zwykłe mechanizmy Laravela.

```text
app/Modules/Catalog/
├── CatalogServiceProvider.php   # dziedziczy po Shared\ModuleServiceProvider; wpis w bootstrap/providers.php
├── Actions/                     # jedna operacja biznesowa na klasę, np. DecrementStock
├── Database/Factories/  Database/Migrations/  Database/Seeders/
├── Events/  Listeners/
├── Http/Controllers/Admin/      # ekrany panelu tego modułu
├── Http/Requests/
├── Models/
├── resources/views/             # widoki pod przestrzenią catalog::
└── routes/web.php  routes/admin.php
```

- `App\Modules\Shared\ModuleServiceProvider` sam wczytuje trasy, widoki i migracje modułu oraz wskazuje
  Laravelowi fabryki modeli w `Database/Factories`. Provider modułu tylko po nim dziedziczy.
- Katalogi z klasami PHP zawsze z wielkiej litery (`Database/Factories`, nie `database/factories`).
  macOS nie rozróżnia wielkości liter, Linux na serwerze tak — zła nazwa działa lokalnie i psuje się dopiero na Forge.
- Moduł zapisuje tylko do swoich tabel. Inny moduł zmienia jego dane wyłącznie przez jego akcje
  (`Checkout` wywołuje `Catalog\Actions\DecrementStock`, nie pisze do `product_variants`).
- Czytać cudze dane przez relację Eloquent wolno.
- Zdarzenia służą do skutków ubocznych: maile, etykiety InPost, logi. Stanu magazynu nie zdejmuje się
  w listenerze z kolejki — musi zejść w tej samej transakcji co potwierdzenie płatności.
- Ekrany panelu należą do modułu, którego dotyczą. `Admin` daje tylko logowanie, układ panelu i menu.
- Testy: `tests/Feature/<Name>` i `tests/Unit/<Name>`.

Pierwsza fala: `Shared` (układ strony, komponenty Blade, formatowanie kwot, SEO), `Settings`, `Admin`,
`Catalog`, `Cart`, `Checkout`, `Payments`, `Shipping`, `MugConfigurator`, `Gifts`, `Content`.
Po świętach: `Workshops`, `CustomOrders`, `Firing`, `Journal`.

## Sesja i cache w plikach

- `SESSION_DRIVER=file` i `CACHE_STORE=file` w `.env` i w `.env.example` — Laravel 13 domyślnie ustawia `database`.
- Pliki leżą w `storage/framework/sessions` i `storage/framework/cache/data`. Tabele `sessions`, `cache`
  i `cache_locks` z domyślnych migracji usuń.
- Nie używaj `Cache::tags()` — magazyn `file` nie obsługuje tagów. `Cache::lock()` działa.
- To rozwiązanie na jeden serwer. Przy drugim serwerze albo load balancerze sesję i cache trzeba przenieść.
- Na serwerze `artisan` uruchamiaj jako użytkownik `forge`, nie root — inaczej pliki sesji i cache
  dostaną złego właściciela i strona zacznie zwracać błędy 500.

## Język kodu

- Cały kod po angielsku: klasy, metody, zmienne, tabele, kolumny, zapisane wartości (kategorie, okazje,
  statusy), klucze ustawień, nazwy tras i widoków, komentarze, testy.
- Po polsku zostaje tylko to, czego nie da się przetłumaczyć bez szkody:
  - adresy URL ze specyfikacji (`/sklep`, `/warsztaty-ceramiczne-krakow`) — to frazy wpisywane w Google;
  - teksty, które czytają klientki i Kasia w panelu (`mellowaura-teksty`);
  - nazwy własne i pojęcia bez odpowiednika: BLIK, Paczkomat, NIP.
- Trasa ma polski adres i angielską nazwę: `Route::get('/sklep', ...)->name('shop.index')`.
- Wartość po angielsku, etykieta po polsku: `crafts` → „Rękodzieło”, `in_progress` → „W realizacji”.
- Polskie nazwy z prototypu i z przykładów w skillach (`doKoszyka`, `zamowienia`, `cena`) tłumacz przy przenoszeniu.

## Treści i dane

- Zdjęcia tylko Kasi — żadnych stockowych, także jako zaślepki. Brak zdjęcia to pusty stan, nie cudze zdjęcie.
- Adres pracowni nigdy na stronie ani w danych strukturalnych. Na stronie opis lokalizacji z ustawień
  („okolice Błoń Krakowskich”), dokładny adres tylko w mailu po zapisie na warsztat.
- Każdy fakt i każda liczba — materiał, zmywarka, mikrofalówka, próg darmowej dostawy, e-mail — pochodzi z panelu,
  nie jest wpisana na stałe w kodzie. Puste pole nie wyświetla się na stronie.
- Żadnych wymyślonych opinii ani liczb opinii. Opinie dopiero z wizytówki Google.
- Ceny z prototypu. Prywatnych danych kontaktowych nie wpisujemy do repozytorium — e-mail uzupełnia się w panelu.

## Zasady, których nie łamiemy

- Stan magazynu zdejmuj dopiero w webhooku potwierdzającym płatność, w transakcji z `lockForUpdate`.
- Webhook płatności: weryfikuj podpis i loguj każde wywołanie.
- Kwoty jako grosze w liczbach całkowitych albo `decimal(10,2)` — nigdy float.
- Pozycja zamówienia kopiuje cenę, napis i kolor z konfiguratora, a nie tylko klucze obce.
- `custom_text` (napis na kubku): limit długości po stronie serwera i escapowanie przy każdym wyświetleniu.
- Przy cenie przekreślonej pokazuj najniższą cenę z 30 dni przed obniżką, liczoną z `price_history`.
- Staging za hasłem i z `X-Robots-Tag: noindex`. Tytuł do 60 znaków, opis do 155, ucinany na granicy słowa.
  Przejścia między stronami to linki `<a href>`, nie `onClick`.
- Bez `AggregateRating` dla samej MellowAury — Google nie pokazuje gwiazdek za opinie o własnej firmie.

## Repozytorium

- Publiczne: `sambor88-glitch/mellow-aura`, gałąź `main`, wypychanie przez SSH.
- Nigdy nie commituj `uploads/` (surowe zrzuty mogą zawierać dane klientek), `.env` ani kluczy — są w `.gitignore`.
- Projekt w Claude Design czyta z tego repozytorium (`github.md`). Zmianę w `.dc.html` wypchnij, a przed pracą
  w Claude Design pobierz aktualną wersję z repo, żeby się nie rozjechały.

## Komendy

Lokalnie wszystko działa w Dockerze (Laravel Sail), bo PHP na Macu to nie 8.4. Strona: http://localhost:8000,
poczta z aplikacji: http://localhost:8025 (Mailpit).

```bash
# pierwsze uruchomienie: vendor/ instaluje kontener z PHP 8.4
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
  -e COMPOSER_HOME=/tmp/composer laravelsail/php84-composer:latest composer install
cp .env.example .env              # potem wartości dla Sail z komentarza na końcu pliku
./vendor/bin/sail up -d           # PHP 8.4, MySQL 8.4 na porcie 3307, Mailpit
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate
npm install && npm run dev        # Vite na Macu, nie w kontenerze
./vendor/bin/sail test            # testy na bazie `testing` w kontenerze MySQL
./vendor/bin/sail down            # zatrzymanie kontenerów
```

- `compose.yaml` buduje `runtimes/8.4`, tak jak na Forge. Sail przy instalacji wybiera najnowsze PHP — nie zmieniaj na 8.5.
- `npm` uruchamiaj tylko na Macu: `node_modules` z macOS nie działa w kontenerze z Linuksem.
- Composer i Artisan tylko przez `./vendor/bin/sail`, żeby zależności liczyły się dla PHP 8.4.

## Gałęzie i środowiska

- `dev` → staging na Forge (`*.on-forge.com`), auto-deploy po każdym pushu, `APP_ENV=staging`, `APP_NOINDEX=true`, dostęp za hasłem.
- `main` → produkcja `mellow-aura.com` (strona produkcyjna powstaje przy starcie), `APP_NOINDEX=false`.
- Pracujesz na `dev`; do `main` scalasz dopiero przetestowane zmiany.
