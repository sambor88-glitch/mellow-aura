# MellowAura

Strona pracowni ceramiki i rękodzieła Katarzyny Samborskiej — Kraków.

## Co jest w repozytorium

| Plik | Co to jest |
| --- | --- |
| `MellowAura.dc.html` | Cała strona: 20 widoków, sklep z własnym koszykiem i BLIK-iem, konfigurator kubka z napisem, warsztaty z terminami, usługi na zamówienie, dziennik i panel właścicielki (8 zakładek — w tym Ustawienia i Treści); galerie produktów z karuzelą na kartach, animacje wejścia i przejść |
| `Plan wdrozenia - Laravel krok po kroku.dc.html` | Ośmiotygodniowy plan wdrożenia na Laravel + Forge: konta, schemat bazy, pułapki, pierwsze kroki |
| `Specyfikacja wdrozenia - MellowAura.dc.html` | Dokument dla osoby wdrażającej: adresy stron z tytułami i opisami, dane strukturalne, zakres panelu, integracje, braki do uzupełnienia |
| `Analiza konkurencji i propozycje - MellowAura.dc.html` | Dokument decyzyjny dla właścicielki: krakowska i polska konkurencja z cenami, nastroje konsumenckie ze źródłami, propozycje na grudzień i na styczeń oraz audyt edytowalności — czego nie da się zmienić w panelu i w jakiej kolejności to dopisać |
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

## Stan projektu

Prototyp o pełnej funkcjonalności interfejsu. Dane panelu żyją w pamięci przeglądarki — po odświeżeniu wracają do stanu wyjściowego. Płatności są symulowane. Zakres pracy wdrożeniowej opisuje sekcja 7 specyfikacji.

## Czego tu nie wrzucamy

Katalog `uploads/` jest wykluczony w `.gitignore`. Trafiają tam surowe zrzuty ekranu, które mogą zawierać dane wrażliwe — nie mają czego robić w publicznym repozytorium.
