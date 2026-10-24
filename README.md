# MellowAura

Strona pracowni ceramiki i rękodzieła Katarzyny Samborskiej — Kraków.

## Co jest w repozytorium

| Plik | Co to jest |
| --- | --- |
| `MellowAura.dc.html` | Cała strona: 20 widoków, sklep z własnym koszykiem i BLIK-iem, konfigurator kubka z napisem, warsztaty z terminami, usługi na zamówienie, dziennik i panel właścicielki (8 zakładek — w tym Ustawienia i Treści); galerie produktów z karuzelą na kartach, animacje wejścia i przejść |
| `Plan wdrozenia - Laravel krok po kroku.dc.html` | Ośmiotygodniowy plan wdrożenia na Laravel + Forge: konta, schemat bazy, pułapki, pierwsze kroki |
| `Specyfikacja wdrozenia - MellowAura.dc.html` | Dokument dla osoby wdrażającej: adresy stron z tytułami i opisami, dane strukturalne, zakres panelu, integracje, braki do uzupełnienia |
| `Certyfikat unikatu.dc.html` | Dwustronna karta A6 do wydruku, dołączana do każdej paczki |
| `Voucher warsztatowy.dc.html` | Pozioma karta A4 — voucher na warsztat do wydruku |
| `Zakup A - krok po kroku.dc.html`, `Zakup B - ekspres BLIK.dc.html` | Dwa warianty ścieżki zakupu; wybrany został wariant B i jest wbudowany w stronę |
| `zdjecia/` | 16 zdjęć produktów i pracowni w formacie WebP, przyciętych i gotowych do użycia |
| `github.md` | Zapis powiązania z repozytorium i notatki o synchronizacji |
| `support.js`, `doc-page.js` | Biblioteki potrzebne do otwarcia plików `.dc.html` |
| `.claude/skills/` | Dziewięć instrukcji — wygląd i UX — z których Claude korzysta sam przy pracy nad stroną, panelem, telefonem i drukiem |
| `CLAUDE.md` | Stos, źródła prawdy i zasady pracy nad kodem — Claude wczytuje go na początku każdej sesji |

## Jak to otworzyć

Pliki `.dc.html` otwierają się bezpośrednio w przeglądarce — wystarczy kliknąć dwa razy. Muszą leżeć w tym samym katalogu co `support.js` (oraz `doc-page.js` dla certyfikatu, vouchera i specyfikacji).

## Skille graficzne i UX

W katalogu `.claude/skills/` leży system wizualny projektu spisany tak, żeby Claude trzymał się go
bez przypominania: paleta, typografia, gotowe komponenty, wzorce ekranów panelu, zasady dla telefonu,
formaty do druku i przygotowanie zdjęć. Obok tego trzy instrukcje UX — ścieżka zakupu i zachowanie
formularzy, teksty w interfejsie oraz dostępność wraz ze skryptem liczącym kontrast palety.
Uruchamiają się same — wystarczy napisać, co ma powstać.
Spis i przykłady poleceń: [`.claude/skills/README.md`](.claude/skills/README.md).

## Aplikacja (Laravel 13)

Kod sklepu leży w `app/Modules/` — opis modułów i zasad w `CLAUDE.md`. Tydzień 1 wąskiego MVP:
tabele sklepu, oferta z prototypu w bazie, staging za hasłem. Na `/` jest na razie techniczna
strona z listą produktów z bazy — w tygodniu 2 zastąpi ją strona główna z prototypu.

### Lokalnie

```bash
composer install
cp .env.example .env && php artisan key:generate   # w .env ustaw dane MySQL
php artisan migrate --seed && php artisan storage:link
php artisan serve                                 # http://localhost:8000
php artisan test
```

### Staging na Forge — krok po kroku

1. Forge → serwer → **New Site**: domena `staging.mellow-aura.com`, PHP 8.4, katalog `/public`, nowa baza MySQL.
2. **Git Repository**: `sambor88-glitch/mellow-aura`, gałąź `main` (do czasu scalenia — gałąź z PR), bez instalacji Composera z kreatora.
3. **Environment**: wklej `.env.example` i zmień:
   `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://staging.mellow-aura.com`,
   dane bazy z Forge, `STAGING_PASSWORD=<hasło>` (tylko tutaj, nie w repo).
4. **Deploy Script** — domyślny z Forge, a w części `artisan` dopisz:
   ```bash
   mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public
   $FORGE_PHP artisan migrate --force
   $FORGE_PHP artisan storage:link
   $FORGE_PHP artisan optimize
   $FORGE_PHP artisan staging:notify-review
   ```
   Ostatnia linia wysyła Kasi maila o nowościach — opis niżej w „Mail do Kasi po deployu”.
5. **SSL**: Let's Encrypt. W Cloudflare rekord `staging` jako A na IP serwera.
6. Pierwszy deploy, potem raz przez SSH jako `forge` (nie root):
   `cd ~/staging.mellow-aura.com && php artisan db:seed --force`.
7. Sprawdź: `https://staging.mellow-aura.com` pyta o hasło (login `mellowaura`), a odpowiedź ma nagłówek
   `X-Robots-Tag: noindex, nofollow` (`curl -I`).

### Mail do Kasi po deployu

Po każdym deployu na staging `artisan staging:notify-review` zbiera z nowych commitów linie
`Review-Note:` i wysyła je Kasi jednym mailem z adresem strony. Commit bez tej linii (poprawka
deployu, refaktor, testy) nie wysyła nic. Błąd wysyłki nie przerywa deployu — mail pójdzie przy następnym.

```text
Dodaj kartę produktu

Review-Note: karta produktu ze zdjęciami, ceną i wyborem koloru
```

Ustawienie (raz):
1. **AWS SES, region eu-central-1**: zweryfikuj domenę `mellow-aura.com` (rekordy DKIM w Cloudflare)
   oraz adresy Kasi i swój. W trybie sandbox SES wysyła tylko do zweryfikowanych adresów — tu to wystarcza.
2. SES → **SMTP settings** → utwórz dane logowania SMTP.
3. Forge → **Environment** stagingu:
   `MAIL_MAILER=smtp`, `MAIL_HOST=email-smtp.eu-central-1.amazonaws.com`, `MAIL_PORT=587`,
   `MAIL_USERNAME` i `MAIL_PASSWORD` z SES, `STAGING_REVIEW_TO=<e-mail Kasi>`,
   `STAGING_REVIEW_REPLY_TO=<Twój e-mail>`, `STAGING_REVIEW_SIGNATURE=Maciej`.
   Adresy wpisuj tylko tam, nie w repo. Na produkcji `STAGING_REVIEW_TO` zostaw pusty.
4. Pierwszy deploy po zmianie zapamiętuje bieżący commit i nic nie wysyła. Kolejne wysyłają notki.
5. Test bez czekania na deploy: ustaw na chwilę `STAGING_REVIEW_TO` na swój adres i przez SSH jako `forge`
   `printf '%s\n' "$(git rev-parse HEAD~5)" > storage/app/staging-review/last-notified-commit && php artisan staging:notify-review`.

## Stan projektu

Prototyp `MellowAura.dc.html` ma pełną funkcjonalność interfejsu. Jego dane panelu żyją w pamięci przeglądarki — po odświeżeniu wracają do stanu wyjściowego. Płatności są symulowane. Zakres pracy wdrożeniowej opisuje sekcja 7 specyfikacji.

## Czego tu nie wrzucamy

Katalog `uploads/` jest wykluczony w `.gitignore`. Trafiają tam surowe zrzuty ekranu, które mogą zawierać dane wrażliwe — nie mają czego robić w publicznym repozytorium.
