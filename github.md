repo: sambor88-glitch/mellow-aura
branch: main

## Last sync

date: 2026-09-14T21:50:46Z

### Updated in this project

- Pobrane z repozytorium: paleta po audycie kontrastu (`#8A7A69→#726456` / `#9B8C7C` na ciemnym tle, `#A0907D→#736454`, `#8C6440→#855F3D`) i zdjęcia w WebP — nałożone na lokalną wersję `MellowAura.dc.html`, która ma nowsze funkcje (animacje, zakładki Ustawienia i Treści, galerie produktów). PNG-i usunięte.
- Pobrane `.claude/skills/` (9 instrukcji wyglądu i UX + `kontrast.py`), nowe `README.md` i `.gitignore`.
- Do wypchnięcia ręcznie do repozytorium: `MellowAura.dc.html` (scalona), `README.md` (dopisane dwa pliki), `github.md`, `Plan wdrozenia - Laravel krok po kroku.dc.html`, `Plan developmentu - MellowAura.dc.html` — dwóch ostatnich w repozytorium jeszcze nie ma.
- Rozjazd w skillach naprawiony: przykłady komponentów w `mellowaura-design`, `-panel`, `-strona`, `-druk`, `-aplikacja` przepisane na paletę AA; sekcja „Ruch" opisuje 9 animacji; `-panel` opisuje zakładki Treści i Ustawienia oraz galerie. Dokumenty do druku i plany też w nowej palecie.
- Lista plików do wgrania i instrukcja krok po kroku: `WYPCHNIJ.md` (nie wgrywać do repo).

## Screen map

| Ekran w projekcie | Źródło |
| --- | --- |
| `MellowAura.dc.html` — Strona główna | teksty i ceny przeniesione z dotychczasowego sklepu; zdjęcia: `zdjecia/*.webp`; paleta: `.claude/skills/mellowaura-design/SKILL.md` |
| `MellowAura.dc.html` — O mnie, Pracownia | teksty autorskie Kasi (edytowalne w panelu → Treści) |
| `MellowAura.dc.html` — Sklep, Produkt, Prezenty | nazwy, ceny i opisy z dotychczasowej oferty; galerie z karuzelą na kartach |
| `MellowAura.dc.html` — Koszyk i płatność | jeden ekran, BLIK / przelew / karta; koszty dostawy z panelu → Ustawienia |
| `MellowAura.dc.html` — Kubek z napisem | podgląd napisu litera po literze, rozmiary, kolor wnętrza |
| `MellowAura.dc.html` — Zestawy, Z Twojej apaszki, Odcisk rośliny | usługi na zamówienie; ceny „od" liczone z cenników |
| `MellowAura.dc.html` — Warsztaty, Na zamówienie, B2B, Wypały, FAQ, Kontakt | listy w formularzach, FAQ i dane kontaktowe edytowalne w panelu |
| `MellowAura.dc.html` — Dziennik, wpis dziennika | kronika: Instagram, notatki, relacje z targów |
| `MellowAura.dc.html` — Panel właścicielki | 8 zakładek: Produkty, Warsztaty, Kubek, Usługi, Wypały, Dziennik, Treści, Ustawienia |
| `Specyfikacja wdrozenia - MellowAura.dc.html` | dokument wdrożeniowy |
| `Plan wdrozenia - Laravel krok po kroku.dc.html` | plan wdrożenia (lokalnie, nie w repo) |
| `Certyfikat unikatu.dc.html`, `Voucher warsztatowy.dc.html` | materiały do druku |
| `.claude/skills/*` | skopiowane 1:1 z repozytorium |

## Kierunek synchronizacji

Pliki projektu nie są wypychane automatycznie — powiązanie działa w stronę czytania.
Właścicielka wgrywa projekt do repozytorium ręcznie.

## Bezpieczeństwo — zamknięte

Stare, publiczne repozytorium `mellow_aura` zostało usunięte 14.09.2026 (potwierdzone: zwraca 404).
Nowe repozytorium `mellow-aura` nie zawiera katalogu `uploads/` ani żadnych danych wrażliwych.

## Sync history

- 2026-09-14T19:14:41Z — projekt wgrany do nowego, czystego repozytorium `mellow-aura` (26 plików, bez `uploads/`).
- 2026-09-14T19:10:53Z — repozytorium `mellow_aura` (publiczne): wgrane 48 plików, następnie usunięte dwa zrzuty ekranu z danymi wrażliwymi; pozostały w historii.
- 2026-09-14T15:54:16Z — pierwszy odczyt `mellow_aura`: repozytorium zawierało wyłącznie `README.md` (13 B), bez kodu UI.

## Uwagi

Brak zapisu commita — `github_get_tree` zwraca hash drzewa, nie commita, więc nie zapisuję zgadywanej wartości.
