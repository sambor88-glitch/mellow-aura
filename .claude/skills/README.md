# Skille graficzne i UX MellowAury

Dziewięć instrukcji, które Claude wczytuje sam, kiedy praca ich dotyczy. Nie trzeba ich uruchamiać
ani pamiętać ich nazw — wystarczy powiedzieć, co ma powstać.

Po co to jest: bez nich każda nowa sekcja, ekran czy wydruk powstaje od zera i wygląda odrobinę
inaczej niż reszta. Z nimi wszystko wychodzi z jednej pracowni — te same kolory, ten sam krój,
te same przyciski, bez powtarzania za każdym razem, jak ma być.

Pakiet dzieli się na dwie części. **Wygląd** pilnuje, żeby nowe ekrany były spójne z resztą.
**UX** pilnuje, czy klient dojdzie do końca zakupu — to nie jest to samo i rozjeżdża się
częściej, niż się wydaje.

### Wygląd

| Skill | Odpowiada za | Włącza się, gdy mówisz |
| --- | --- | --- |
| `mellowaura-design` | kierunek „Aura”: paleta, typografia, szkło, efekty i ruch, gotowe komponenty | „ładniej", „spójnie", „w stylu strony", „animacja" |
| `mellowaura-strona` | nowe podstrony i sekcje sklepu | „dodaj stronę", „nowa sekcja", „landing" |
| `mellowaura-panel` | panel właścicielki, CRM, zamówienia | „panel", „lista klientów", „statystyki" |
| `mellowaura-aplikacja` | telefon, dotyk, aplikacja na ekranie głównym | „na telefonie", „aplikacja", „mobilnie" |
| `mellowaura-druk` | certyfikaty, vouchery, metki, wizytówki | „do wydruku", „PDF do drukarni" |
| `mellowaura-zdjecia` | zdjęcia produktów, waga plików, opisy | „dodaj zdjęcia", „wolno się ładuje" |

### UX i zachowanie

| Skill | Odpowiada za | Włącza się, gdy mówisz |
| --- | --- | --- |
| `mellowaura-ux` | ścieżka zakupu, formularze, błędy, potwierdzenia | „ludzie nie kupują", „za dużo klikania" |
| `mellowaura-teksty` | etykiety, komunikaty, opisy — głos właścicielki | „napisz to po ludzku", „co ma być na przycisku" |
| `mellowaura-dostepnosc` | kontrast, klawiatura, czytniki ekranu, przepisy | „czy to czytelne", „WCAG", „dostępność" |

`mellowaura-dostepnosc` ma przy sobie skrypt `kontrast.py` — liczy kontrast dowolnej pary kolorów
i podaje poprawiony odcień, jeśli para nie przechodzi normy:

```bash
python3 .claude/skills/mellowaura-dostepnosc/kontrast.py --fix '#A0907D'
```

`mellowaura-design` jest podstawą dla wyglądu, `mellowaura-ux` dla zachowania. Jeśli zmieniasz kolor,
krój pisma albo efekt, zmieniasz go **w `mellowaura-design`**, a nie w dziewięciu miejscach.

## Przykłady poleceń

```
Dodaj podstronę „Prezenty firmowe" z formularzem zapytania dla kawiarni.
Zrób w panelu zakładkę z zamówieniami: kto, co, za ile, na jakim etapie.
Strona wolno się ładuje na telefonie — napraw to.
Sprawdź, gdzie ludzie mogą się gubić w ścieżce zakupu.
Ten komunikat błędu brzmi korporacyjnie. Napisz go po ludzku.
Sprawdź kontrast na karcie produktu.
Przygotuj metkę do zawieszenia przy kubku, A7, z kodem QR.
Ten ekran wygląda obco na tle reszty. Popraw.
```

## Co dokładają skille wbudowane w Claude

Działają razem z powyższymi, nic nie trzeba instalować:

- **`design`** — makiety na płótnie do klikania: kilka ekranów obok siebie, do obejrzenia zanim
  cokolwiek powstanie w kodzie. Dobre na decyzję „tak czy nie" przed robotą.
- **`dataviz`** — wykresy sprzedaży i raporty, czytelne także po wydrukowaniu na czarno-biało.
- **`canvas-design`** — plakaty i grafiki na Instagram jako pliki PNG i PDF.
- **`theme-factory`** — spójny motyw kolorystyczny dla dokumentów i prezentacji.
- **`pdf`, `docx`, `xlsx`** — cenniki, umowy, zestawienia do księgowości.

## Co można jeszcze dołożyć z rynku

Wtyczki z katalogu Claude — instaluje się je raz, klikając w karcie propozycji:

| Wtyczka | Kiedy ma sens |
| --- | --- |
| **Design** | audyt dostępności, krytyka projektu, system projektowy, specyfikacja dla wdrożeniowca |
| **Figma** | gdy pojawi się grafik i projekty zaczną powstawać w Figmie |
| **Canva** | posty i grafiki na social media w gotowych szablonach marki |
| **Modern Web Guidance** | bieżące standardy przeglądarek, od zespołu Google Chrome |
| **SearchFit SEO** | audyt widoczności w Google, dane strukturalne, plan treści |
| **Brand Voice** | spisanie i pilnowanie tonu, którym mówi marka |

Nie instaluj wszystkiego naraz. Każda wtyczka zajmuje miejsce w pamięci rozmowy —
bierz tę, która odpowiada pracy na najbliższe tygodnie.
