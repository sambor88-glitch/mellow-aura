# MellowAura

Strona pracowni ceramiki i rękodzieła Katarzyny Samborskiej — Kraków.

## Co jest w repozytorium

| Plik | Co to jest |
| --- | --- |
| `MellowAura.dc.html` | Cała strona: 20 widoków, sklep z własnym koszykiem i BLIK-iem, konfigurator kubka z napisem, warsztaty z terminami, usługi na zamówienie, dziennik i panel właścicielki |
| `Specyfikacja wdrozenia - MellowAura.dc.html` | Dokument dla osoby wdrażającej: adresy stron z tytułami i opisami, dane strukturalne, zakres panelu, integracje, braki do uzupełnienia |
| `Certyfikat unikatu.dc.html` | Dwustronna karta A6 do wydruku, dołączana do każdej paczki |
| `Voucher warsztatowy.dc.html` | Pozioma karta A4 — voucher na warsztat do wydruku |
| `Zakup A - krok po kroku.dc.html`, `Zakup B - ekspres BLIK.dc.html` | Dwa warianty ścieżki zakupu; wybrany został wariant B i jest wbudowany w stronę |
| `zdjecia/` | 16 zdjęć produktów i pracowni w formacie WebP, przyciętych i gotowych do użycia |
| `github.md` | Zapis powiązania z repozytorium i notatki o synchronizacji |
| `support.js`, `doc-page.js` | Biblioteki potrzebne do otwarcia plików `.dc.html` |

## Jak to otworzyć

Pliki `.dc.html` otwierają się bezpośrednio w przeglądarce — wystarczy kliknąć dwa razy. Muszą leżeć w tym samym katalogu co `support.js` (oraz `doc-page.js` dla certyfikatu, vouchera i specyfikacji).

## Stan projektu

Prototyp o pełnej funkcjonalności interfejsu. Dane panelu żyją w pamięci przeglądarki — po odświeżeniu wracają do stanu wyjściowego. Płatności są symulowane. Zakres pracy wdrożeniowej opisuje sekcja 7 specyfikacji.

## Czego tu nie wrzucamy

Katalog `uploads/` jest wykluczony w `.gitignore`. Trafiają tam surowe zrzuty ekranu, które mogą zawierać dane wrażliwe — nie mają czego robić w publicznym repozytorium.
