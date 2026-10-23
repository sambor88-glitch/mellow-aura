# Stan prac — MellowAura

Stan na 23.10.2026. Do startu (10 listopada) zostało 18 dni.

## Gdzie toczą się prace

- **GitLab** (`gitlab.com/sambor88/mellow-aura`, prywatny): tu toczy się bieżąca praca, także na gałęziach,
  których nie ma na GitHubie (np. `konrad/*`).
- **GitHub** (`sambor88-glitch/mellow-aura`): przy każdym pushu workflow `mirror-to-gitlab.yml` kopiuje
  gałęzie i tagi na GitLab. Nadpisuje gałęzie o tej samej nazwie, a gałęzi istniejących tylko na GitLabie nie rusza.
- **Kod Laravela** jest na gałęzi `dev`. Gałąź `main` to wciąż tylko prototyp i dokumenty.
- **Zadania**: Jira, projekt MA — https://maciej-samborski.atlassian.net/issues?jql=project%20%3D%20MA

> Ten raport powstał z GitHuba i z Jiry. GitLaba nie dało się odczytać (brak dostępu z sesji),
> więc gałęzie i merge requesty, które są tylko na GitLabie, nie są tu uwzględnione.

## Gałąź `dev` (z GitHuba)

- 124 commity więcej niż `main`, ostatni z 9.10.2026.
- 15 modułów w `app/Modules`, 88 plików w `tests/`.

| Moduł | Pliki | Uwagi |
| --- | --- | --- |
| Checkout | 112 | |
| Catalog | 73 | |
| Content | 65 | |
| Gifts | 52 | |
| Shared | 49 | |
| Settings | 24 | |
| Admin | 21 | |
| MugConfigurator | 21 | |
| Cart | 19 | |
| Monitoring | 14 | |
| Payments | 13 | Stripe niegotowy (MA-97…103) |
| Localization | 10 | |
| Firing | 9 | po świętach |
| Workshops | 9 | po świętach |
| Consent | 8 | |
| Shipping | — | **modułu nie ma** (MA-10, MA-72) |

Liczba plików mówi tylko o objętości kodu, nie o tym, czy moduł działa.

## Jira MA

- 157 zadań: 49 zamkniętych, reszta otwarta.
- Epiki modułów (MA-3…MA-13) wciąż mają status „Do zrobienia”, choć kod dla nich jest na `dev`.
  Tablica nie pokazuje prawdziwego stanu prac.

## Co zostało do zakodowania przed startem

1. **Payments: Stripe z BLIK-iem** — MA-100 (PaymentIntent w PLN i groszach), MA-101 (kod BLIK na stronie,
   licznik 60 s), MA-102 (webhook: podpis, bez CSRF, zejście stanu w transakcji), MA-103 (testy nieudanych płatności),
   MA-99 (usunąć Przelewy24 z tekstów).
2. **Shipping: InPost** — MA-10 i widget paczkomatu MA-72.
3. **Maile przez Postmark** — MA-53 (w toku).
4. **Kopie zapasowe** — MA-79.
5. **SEO przed przełączeniem domeny** — przekierowania ze SumUp (MA-109), kontrola indeksowania (MA-114).
6. **Próba i start** — próbne zamówienie (MA-83), Cloudflare i domena (MA-84), start (MA-85).

## Ryzyka

- Płatności miały działać w tygodniu 4 planu (6–12.10), a wciąż są największą dziurą.
- Zakres się rozrósł: wersja angielska, ceny w euro i sprzedaż do UE (MA-126/127) weszły przed Stripe'em,
  choć nie było ich w planie na święta.
- Na `dev` od dwóch tygodni nie ma commitów. Jeśli praca idzie na GitLabie, stan może być lepszy niż w tym raporcie.

## Rekomendacja

Najpierw MA-100 i MA-102 (PaymentIntent i webhook Stripe), potem InPost. Następnie przesunąć w Jirze
epiki modułów, które mają już kod.

## Otwarte PR-y na GitHubie

- #19 — widok na tablecie i telefonie
- #6 — hosting na AWS w dokumentach
