# Skille graficzne MellowAury

Sześć instrukcji, które Claude wczytuje sam, kiedy praca ich dotyczy. Nie trzeba ich uruchamiać
ani pamiętać ich nazw — wystarczy powiedzieć, co ma powstać.

Po co to jest: bez nich każda nowa sekcja, ekran czy wydruk powstaje od zera i wygląda odrobinę
inaczej niż reszta. Z nimi wszystko wychodzi z jednej pracowni — te same kolory, ten sam krój,
te same przyciski, bez powtarzania za każdym razem, jak ma być.

| Skill | Odpowiada za | Włącza się, gdy mówisz |
| --- | --- | --- |
| `mellowaura-design` | paleta, typografia, gotowe komponenty | „ładniej", „spójnie", „w stylu strony" |
| `mellowaura-strona` | nowe podstrony i sekcje sklepu | „dodaj stronę", „nowa sekcja", „landing" |
| `mellowaura-panel` | panel właścicielki, CRM, zamówienia | „panel", „lista klientów", „statystyki" |
| `mellowaura-aplikacja` | telefon, dotyk, aplikacja na ekranie głównym | „na telefonie", „aplikacja", „mobilnie" |
| `mellowaura-druk` | certyfikaty, vouchery, metki, wizytówki | „do wydruku", „PDF do drukarni" |
| `mellowaura-zdjecia` | zdjęcia produktów, waga plików, opisy | „dodaj zdjęcia", „wolno się ładuje" |

`mellowaura-design` jest podstawą — pozostałe pięć się do niego odwołuje. Jeśli zmieniasz kolor
albo krój pisma marki, zmieniasz go **tam**, a nie w sześciu miejscach.

## Przykłady poleceń

```
Dodaj podstronę „Prezenty firmowe" z formularzem zapytania dla kawiarni.
Zrób w panelu zakładkę z zamówieniami: kto, co, za ile, na jakim etapie.
Strona wolno się ładuje na telefonie — napraw to.
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
