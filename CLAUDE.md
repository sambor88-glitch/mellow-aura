# MellowAura

Sklep i strona pracowni ceramiki Kasi Samborskiej z Krakowa. W repozytorium jest prototyp, dokumentacja
i szkielet aplikacji Laravel 13 (katalogi `app/`, `config/`, `public/` itd.). Serwer serwuje wyłącznie `public/`.

## Źródła prawdy

| Plik | Rozstrzyga |
| --- | --- |
| `Aura - strona glowna.html`, `Aura - ekrany.html` | wygląd i ruch (kierunek „Aura” od 22.09.2026) — przenoś do Blade, nie projektuj od nowa |
| `MellowAura.dc.html` | teksty, ceny, przebieg i zachowanie każdego ekranu; jego wygląd zastąpiła Aura |
| `Specyfikacja wdrozenia - MellowAura.dc.html` | adresy, tytuły, opisy, JSON-LD, zakres panelu, integracje |
| `Plan wdrozenia - Laravel krok po kroku.dc.html` | stos, schemat bazy, harmonogram do 10 listopada |
| `Plan wdrozenia - dwujezycznosc i sprzedaz UE.dc.html` | drugi język, druga waluta, strefy wysyłki, próg WSTO — rozstrzyga wszystko, co dotyczy `/en/` i euro |
| `.claude/skills/` | paleta, efekty, komponenty, UX, teksty, dostępność — kolory, kroje i efekty zmieniasz tylko w `mellowaura-design` |

Pliki `.dc.html` otwierają się w przeglądarce i muszą leżeć obok `support.js`
(dokumenty do druku także obok `doc-page.js`).

## Stos

Laravel 13 + Blade, Alpine.js, GSAP z ScrollTrigger, Lenis i three.js (tylko kubek 3D) przez npm, MySQL, własny panel na Blade (nie Filament), paczki Spatie: medialibrary,
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
- Mail dziedziczy po `Shared\Mail\QueuedMail`: idzie przez kolejkę, ponawia się przez dwie godziny, a jeśli nie wyjdzie,
  trafia do panelu „Niewysłane maile” z opisem z `description()`. Wyjątek to odpowiedź na reklamację — Kasia musi
  od razu wiedzieć, czy wyszła. Alerty techniczne (`Monitoring\Support\Alerts`) idą od razu, nigdy przez kolejkę.
- Ekrany panelu należą do modułu, którego dotyczą. `Admin` daje tylko logowanie, układ panelu i menu.
- Testy: `tests/Feature/<Name>` i `tests/Unit/<Name>`.

Pierwsza fala: `Shared` (układ strony, komponenty Blade, formatowanie kwot, SEO), `Localization` (prefiks
`/en/`, slugi per język, przełącznik — wchodzi przed modułami z tłumaczonymi treściami), `Settings`, `Admin`,
`Catalog`, `Cart`, `Checkout`, `Payments`, `Shipping`, `MugConfigurator`, `Gifts`, `Content`, `Consent` (zgody na cookies i Google Analytics),
`Monitoring` (niewysłane maile w panelu, alerty o błędach, pilnowanie kolejki i harmonogramu).
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

## Dwa języki i dwie waluty

Szczegóły w `Plan wdrozenia - dwujezycznosc i sprzedaz UE.dc.html`. Tu zasady, których nie wolno obejść w kodzie.

- Polski bez prefiksu, angielski pod `/en/`. Trasę piszesz raz, po polsku, w module. Angielski adres dopisujesz
  w `config/localization.php` (`paths.en`) — moduł `Localization` robi z niego bliźniaka `en.<nazwa>` z tym samym
  kontrolerem. Strony spoza tej listy istnieją tylko po polsku.
- `route('shop.index')` na angielskiej stronie sam daje `/en/shop`. Nie pisz `route('en.…')` ani `url('/')` —
  do strony głównej prowadzi `route('home')`.
- Sprawdzając bieżącą stronę, pytaj o obie nazwy: `request()->routeIs($p, Locales::current().'.'.$p)`.
- Teksty interfejsu w `lang/pl` i `lang/en` modułu, jako `__('modul::plik.klucz')`. Moduł wczytuje je sam.
- `APP_LOCALES` włącza języki: produkcja `pl`, lokalnie i staging `pl,en`. Testy chodzą z `pl,en`.
- Waluta wynika z języka: PL to PLN, EN to EUR. Nie ma osobnego przełącznika waluty i nie ma koszyka
  z mieszanymi walutami.
- Ceny w euro wpisuje Kasia w panelu. Żadnego przeliczania po kursie w locie.
- Cena mieszka w tabeli `prices` (`product_variant_id`, `currency`, `amount_minor`), historia w
  `price_history` osobno dla każdej waluty — Omnibus liczy się per waluta.
- Brak tłumaczenia albo brak ceny w EUR ukrywa pozycję na `/en/`. Nigdy nie psuje wersji polskiej
  i nigdy nie podstawia polskiego tekstu pod angielski adres.
- Warsztaty, voucher, wypał, gastronomia, odcisk rośliny i apaszka nie istnieją na `/en/`. Menu budujemy
  z konfiguracji per język, nie ukrywamy pozycji CSS-em.
- Przełącznik języka na stronie bez odpowiednika prowadzi do najbliższej sensownej strony, nigdy na stronę główną.
- Język podpowiadamy z nagłówka `Accept-Language`, nigdy z adresu IP, i nigdy nie przekierowujemy automatycznie.
- BLIK istnieje tylko w PLN. Lista metod płatności filtruje się walutą koszyka.
- Każde zamówienie zapisuje kraj dostawy i stawkę VAT na pozycji — także wtedy, gdy stawka wynosi zero.
  Bez tego nie da się odtworzyć obrotu WSTO, a próg 10 000 EUR rocznie przekracza się niezauważenie.

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
./vendor/bin/sail artisan queue:listen  # bez tego maile czekają w kolejce i nie docierają do Mailpit
./vendor/bin/sail test            # testy na bazie `testing` w kontenerze MySQL
./vendor/bin/sail down            # zatrzymanie kontenerów
```

- `compose.yaml` buduje `runtimes/8.4`, tak jak na Forge. Sail przy instalacji wybiera najnowsze PHP — nie zmieniaj na 8.5.
- `npm` uruchamiaj tylko na Macu: `node_modules` z macOS nie działa w kontenerze z Linuksem.
- Composer i Artisan tylko przez `./vendor/bin/sail`, żeby zależności liczyły się dla PHP 8.4.

## Gałęzie i środowiska

- `dev` → staging na Forge (`mellowaura-dev.on-forge.com`), auto-deploy po każdym pushu, `APP_ENV=staging`, `APP_NOINDEX=true`,
  `APP_LOCALES=pl,en`, dostęp za hasłem.
- `main` → produkcja `mellow-aura.com` (strona produkcyjna powstaje przy starcie), `APP_NOINDEX=false`.
- Pracujesz na `dev`; do `main` scalasz dopiero przetestowane zmiany.
