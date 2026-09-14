repo: sambor88-glitch/mellow_aura
branch: main

## Last sync

date: 2026-09-14T19:10:53Z

### Updated w tym projekcie

- Repozytorium jest praktycznie puste — zawiera tylko `README.md` (13 B), bez kodu UI, stylów ani komponentów. Nie było czego odtwarzać.
- Treść i ofertę przeniesiono z dotychczasowego sklepu online: teksty „O mnie" i „Warsztaty", nazwy i ceny produktów.
- Zdjęcia: 16 plików wgranych przez użytkowniczkę do katalogu `zdjecia/`. Żadnych zasobów zewnętrznych.
- Zbudowano serwis `MellowAura.dc.html` (17 widoków, własny koszyk z BLIK-iem, panel właścicielki) oraz `Specyfikacja wdrozenia - MellowAura.dc.html`.
- Decyzja: nowy sklep powstaje na własnej domenie, z własną bramką płatności. Poprzednia platforma jest wygaszana i nie występuje w specyfikacji.

## Screen map

| Ekran w projekcie | Źródło |
| --- | --- |
| `MellowAura.dc.html` — Strona główna | teksty i ceny przeniesione z dotychczasowego sklepu; zdjęcia: `zdjecia/` |
| `MellowAura.dc.html` — O mnie | tekst autorski Kasi, przeniesiony dosłownie |
| `MellowAura.dc.html` — Pracownia | hasła autorskie Kasi, przeniesione dosłownie |
| `MellowAura.dc.html` — Sklep / Produkt | nazwy, ceny i opisy produktów z dotychczasowej oferty |
| `MellowAura.dc.html` — Koszyk i płatność | nowy projekt: jednoekranowa ścieżka, BLIK / przelew online / karta |
| `MellowAura.dc.html` — Kubek z napisem, Prezenty, Zestawy | nowe sekcje |
| `MellowAura.dc.html` — Warsztaty, Na zamówienie, B2B, Wypały, Dziennik, FAQ | nowe sekcje; ceny i terminy do potwierdzenia |
| `MellowAura.dc.html` — Panel właścicielki | nowy projekt |
| `Specyfikacja wdrozenia - MellowAura.dc.html` | dokument dla wdrożenia: adresy, meta, dane strukturalne |

## Kierunek synchronizacji

Pliki projektu NIE są wypychane automatycznie — powiązanie działa w stronę czytania.
Właścicielka wgrała projekt do repozytorium ręcznie 14 września 2026; w repozytorium jest 48 plików.

## UWAGA BEZPIECZEŃSTWA

Repozytorium jest PUBLICZNE. Dwa zrzuty ekranu wgrane omyłkowo (tajny klucz klienta Google OAuth
`GOCSPX-…` oraz panel wewnętrznego CRM-a z logami serwera) zostały usunięte commitem 14.09.2026
i nie występują już w aktualnym stanie repozytorium.

Sprawa NIE jest domknięta samym usunięciem: oba pliki pozostają w historii gita i są publicznie
odczytywalne z wcześniejszych commitów. Wymagane działania:
1. unieważnić klucz OAuth w Google Cloud i wygenerować nowy,
2. opcjonalnie wyczyścić historię — usunąć i wrzucić repozytorium od nowa albo użyć `git filter-repo`.

Pozostałe 20 plików w `uploads/` to zdjęcia produktów i zrzuty ekranu strony — bez danych wrażliwych.

## Uwagi

Brak zapisu commita — `github_get_tree` zwraca hash drzewa, nie commita, więc nie zapisuję zgadywanej wartości.
