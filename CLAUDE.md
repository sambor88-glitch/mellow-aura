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

Płatności tylko przez Przelewy24 (BLIK, karta, szybki przelew) plus przelew tradycyjny. **Bez Stripe** — decyzja Macieja.
Stripe usunięty (MA-167). Bramka `Payments\Gateways\Przelewy24Gateway` (rejestracja, BLIK kodem, strona P24,
powiadomienie z podpisem i `transaction/verify`) jest w kodzie, ale nie była jeszcze uruchomiona na sandboxie — konto P24
czeka na JDG Kasi (MA-26, MA-98). Bez kluczy płaci `Payments\Gateways\TestGateway`, który nie działa na produkcji —
dlatego `dev` nie idzie do `main`, dopóki bramka nie przejdzie testów w sandboxie.

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
- Cenę w euro produkt ma na jeden z dwóch sposobów. Domyślnie wpisuje ją Kasia w panelu. Z zaznaczonym
  „Cena w euro z kursu NBP” (`products.euro_from_rate`) liczy ją `Catalog\Actions\ConvertEuroPrices` z ceny w złotych
  po średnim kursie NBP (tabela A, `exchange_rates`), zaokrągloną do pełnego euro z końcówką ,90. Kurs pobiera co godzinę
  `catalog:euro-rate`. Przeliczona cena zapisuje się w `prices` jak każda inna — nigdy nie liczymy jej w locie przy wyświetlaniu,
  bo koszyk, zamówienie i Omnibus muszą widzieć tę samą kwotę.
- Cena w złotych zostaje w wariancie (`price_gross`, `compare_at_price`), tak jak polski tekst zostaje w tabeli
  katalogu. Inne waluty mieszkają w `prices` (`product_variant_id`, `currency`, `amount_minor`, `compare_at_minor`).
  Historia w `price_history` ma kolumnę `currency` — Omnibus liczy się per waluta.
- Cenę czytasz przez `$variant->price()` i `compareAtPrice()` — w walucie strony. Kwotę pokazujesz przez
  `Money::format($kwota, $waluta)`; bez waluty to złote. `price_gross` to zawsze złote, nigdy „cena strony”.
- Koszyk liczy w walucie strony. Pozycja bez ceny w tej walucie (`CartLine::pricedIn`) wypada z koszyka na tej
  stronie i wraca na stronie w swojej walucie. Kubek z napisem ma `price_eur` przy rozmiarze w ustawieniach;
  zestawy i pakowanie mają na razie tylko złote.
- Brak tłumaczenia albo brak ceny w EUR ukrywa pozycję na `/en/`. Nigdy nie psuje wersji polskiej
  i nigdy nie podstawia polskiego tekstu pod angielski adres.
- Warsztaty, voucher, wypał, gastronomia, odcisk rośliny, apaszka, dziennik i „Szukam prezentu” nie istnieją
  na `/en/`. Menu budujemy z konfiguracji per język, nie ukrywamy pozycji CSS-em.
- Przełącznik języka na stronie bez odpowiednika prowadzi do najbliższej sensownej strony, nigdy na stronę główną.
- Język podpowiadamy z nagłówka `Accept-Language`, nigdy z adresu IP, i nigdy nie przekierowujemy automatycznie.
- BLIK istnieje tylko w PLN. Lista metod płatności filtruje się walutą koszyka (`PaymentMethod::for`): PLN to BLIK,
  Przelewy24, karta i przelew tradycyjny; EUR to karta i Przelewy24. Przelew tradycyjny w euro czeka na konto (MA-124).
- Zamówienie zapisuje `currency` i `locale`. Każdą kwotę zamówienia pokazujesz przez `$order->money($kwota)`,
  nigdy przez gołe `Money::format` — w mailach i w panelu też.
- Mail do klientki idzie w języku zamówienia (`Checkout\Mail\Concerns\InOrderLanguage`): list ma szablon
  na język (`mail/en/…`), wspólne kawałki biorą teksty z `checkout::mail`. Mail do Kasi zawsze po polsku —
  `QueuedMail` ma domyślnie `pl`. Certyfikat renderuje się w języku zamówienia.
- Dostawa w euro to `price_eur` przy metodzie w ustawieniach i angielska nazwa w `checkout::shipping`. Bez jednego
  z nich metody nie ma w angielskiej kasie. Darmowa wysyłka działa tylko w złotych.
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

## Kopie zapasowe

- Co noc `backup:run` (spatie/laravel-backup): zrzut bazy i `storage/app/{public,private}` w archiwum z hasłem
  `BACKUP_ARCHIVE_PASSWORD`, na dysk `backups` (S3 eu-central-1, bucket i klucz z `infra/backups`, Terraform).
- W poniedziałek `monitoring:verify-backup` odtwarza najnowszą kopię w osobnej bazie `DB_RESTORE_DATABASE` i sprawdza
  tabele i zdjęcia. Ta baza nigdy nie może być bazą sklepu — komenda usuwa w niej wszystkie tabele.
- Problemy z kopią idą przez `Monitoring\Support\Alerts`, nie przez maile paczki. Wdrożenie i odtwarzanie: `infra/backups/README.md`.

## Repozytorium

- Publiczne: `sambor88-glitch/mellow-aura`, gałąź `main`, wypychanie przez SSH.
- Nigdy nie commituj `uploads/` (surowe zrzuty mogą zawierać dane klientek), `.env` ani kluczy — są w `.gitignore`.
- Projekt w Claude Design czyta z tego repozytorium (`github.md`). Zmianę w `.dc.html` wypchnij, a przed pracą
  w Claude Design pobierz aktualną wersję z repo, żeby się nie rozjechały.

## Jira i czas pracy

- Zadania są w Jirze, projekt MA. Każde nowe zadanie i podzadanie dostaje estymatę (`Original Estimate`)
  od razu przy zakładaniu. Bez estymaty zadanie nie istnieje.
- Każda praca nad zadaniem ma worklog z prawdziwym czasem i datą. Zadanie nie przechodzi na „Gotowe”
  bez worklogu.
- Czas mierzymy od 24.10.2026. Worklogi sprzed tej daty nie są prawdziwym pomiarem — nie licz z nich godzin.
- Raport godzin jest co poniedziałek: zalogowany czas a estymata, zadania bez estymaty, zamknięte bez worklogu.
- Worklogi loguje Claude sam, bez pytania Macieja. Pod koniec pracy w sesji: dla każdego zadania, nad którym
  była praca, osobny worklog. Czas i godzina startu z godzin wiadomości w sesji (`list_events`), nie z szacunku.
  Przerwy dłuższe niż 15 minut bez wiadomości nie wchodzą do czasu. Praca bez zadania w Jirze — najpierw
  zadanie z estymatą, potem worklog.

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
- Testy potrzebują Vite: działającego `npm run dev` albo zbudowanego `npm run build`. Bez żadnego z nich część testów
  stron pada z `ViteManifestNotFoundException`. CI przed testami robi `npm ci && npm run build`.

## Gałęzie i środowiska

- `dev` → staging na Forge (`mellowaura-dev.on-forge.com`), auto-deploy po każdym pushu, `APP_ENV=staging`, `APP_NOINDEX=true`,
  `APP_LOCALES=pl,en`, dostęp za hasłem.
- `main` → produkcja `mellow-aura.com` (strona produkcyjna powstaje przy starcie), `APP_NOINDEX=false`.
  Do startu `mellow-aura.com` przekierowuje do starego sklepu na SumUp.
- Pracujesz na `dev`; do `main` scalasz dopiero przetestowane zmiany. `main` ma tylko szkielet z tygodnia 1.

## Staging na Forge — stan na 25.10.2026

- Na Forge jest jedna strona: `mellowaura-dev.on-forge.com`. Drugą (`mellow-aura-staging.on-forge.com`) Maciej usunął 25.10.
  Nie zakładaj drugiej strony stagingu.
- Hasło: `Shared\Http\Middleware\ProtectStaging` (MA-168), zmienne `STAGING_USER` (domyślnie `mellowaura`) i `STAGING_PASSWORD`.
  Bez hasła przechodzą tylko `/up` i powiadomienie Przelewy24 `/przelewy24/powiadomienie` (`ProtectStaging::OPEN`).
- Bez kluczy bramki płatności kasa płaci sama: zamówienie od razu ma status „Zapłacone”, pieniądze nie są pobierane.
  Z kluczami `PRZELEWY24_*` (sandbox: `PRZELEWY24_SANDBOX=true`) płaci `Payments\Gateways\Przelewy24Gateway`.
  Kody BLIK na próbę (`Checkout\Actions\SimulatePayment`): `000000` — bank odrzuca, `111111` — nikt nie potwierdza
  w aplikacji, więc widać ekran `/zamowienie/blik` z licznikiem 2 minut i polem na nowy kod (MA-101).
  `PRZELEWY24_BLIK_ON_SITE=false` — BLIK na stronie P24 zamiast kodu w kasie, dopóki P24 nie włączy BLIK Level 0.
- Maile: Postmark (`MAIL_MAILER=postmark`, `POSTMARK_API_KEY`), konto na kasia@mellow-aura.com, serwer „My First Server”.
  `MAIL_REDIRECT_TO=kasia@mellow-aura.com` — każdy mail ze stagingu trafia do Kasi z adresatem w temacie `[do: …]`.
  Wniosek o zatwierdzenie konta wysłany 25.10 (wg Macieja; odpowiedź Postmarka do sprawdzenia). Do zatwierdzenia maile dochodzą tylko na adresy @mellow-aura.com.
- Worker kolejki: Forge → strona → Processes (`queue:work database`). Deploy wysyła `queue:restart`, ale 25.10 worker
  i tak został na starym wdrożeniu bez tokenu Postmarka (w logu `User is not set`, maile zamówień nie wychodziły).
  Po każdej zmianie w Environment: Deploy, potem **Restart workera w Processes**, potem próbne zamówienie
  i sprawdzenie Postmark → Activity.
- Log aplikacji: Forge → strona → Observe → Logs → Site Log. Wpisy z alertów zaczynają się od `Błąd:` i `Komunikat:`.
- Mapa paczkomatów w kasie (MA-72): `INPOST_GEOWIDGET_TOKEN` z InPost Managera. Pusty token = samo pole na kod paczkomatu.
- Tokenów, haseł i kluczy nie wklejamy do czatu, Jiry ani repozytorium. Na zrzutach ekranu zamazujemy.
