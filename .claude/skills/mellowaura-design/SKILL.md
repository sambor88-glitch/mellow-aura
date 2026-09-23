---
name: mellowaura-design
description: System wizualny MellowAura w kierunku „Aura” — paleta, typografia, szkło, rozmycia, poświata, ruch przy przewijaniu, kubek 3D i gotowe komponenty wyciągnięte z plików „Aura - strona glowna.html” i „Aura - ekrany.html”. Użyj ZAWSZE, zanim narysujesz albo zmienisz cokolwiek widocznego: nową sekcję, ekran, przycisk, kartę, formularz, animację, materiał do druku, grafikę na social media. Także wtedy, gdy ktoś prosi o „ładniej”, „spójnie z resztą”, „w stylu strony”, „z efektem”, „animacja”, „paralaksa” albo o kolory i czcionki marki.
---

# System wizualny MellowAura — kierunek „Aura”

Ten dokument jest źródłem prawdy dla wyglądu. Od 22.09.2026 obowiązuje kierunek **Aura**, który
Maciej zaakceptował zamiast spokojnego stylu z `MellowAura.dc.html`. Wzorcowe strony leżą w repozytorium:

| Plik | Co pokazuje |
| --- | --- |
| `Aura - strona glowna.html` | hero z rosnącym kadrem, poświata i ziarno, sklep w poziomie, kubek 3D, warsztaty, stopka |
| `Aura - ekrany.html` | karta produktu, kasa z BLIK-iem na jednym ekranie, potwierdzenie zamówienia |

Oba pliki otwierają się wprost w przeglądarce (obok folderu `zdjecia/`). Teksty, ceny i przebieg ekranów
nadal pochodzą z `MellowAura.dc.html` — Aura zmienia **jak to wygląda i się rusza**, nie **co jest napisane**.

Nie wprowadzaj nowego koloru, kroju, promienia ani efektu spoza tych list. Kierunek jest odważny,
ale nie przypadkowy — każdy efekt ma tu swoje miejsce i uzasadnienie.

## Skąd bierze się ten styl

Nazwa mówi wszystko: **mellow** — miękko, ciepło, powoli; **aura** — poświata wokół rzeczy zrobionej ręką.
Z tego wynikają cztery decyzje:

1. **Papier i poświata.** Tła są ciepłe i piaskowe, nigdy białe. Nad nimi dryfują rozmyte plamy
   w kolorach palety (róż, karmel, piasek) i leży delikatne ziarno jak na papierze czerpanym.
   Czysta biel (`#fff`) tylko w polach formularzy.
2. **Cienka szeryfowa kursywa jako głos autorki.** Newsreader 300 mówi, Instrument Sans informuje.
   Bez zmian — to najsilniejszy znak marki i przetrwał zmianę kierunku.
3. **Szkło zamiast ramek.** Elementy unoszące się nad treścią (nagłówek, cena na zdjęciu, podsumowanie
   zamówienia, koszyk) są z matowego szkła z rozmyciem tła. Karty w spoczynku dalej trzymają się obrysu.
4. **Ruch jak glina.** Rzeczy wyłaniają się spokojnie, a litery wbijają się jak stempel.
   Rozmycie przy przewijaniu zostaje tylko na znaku słownym w hero — reszta się rozjaśnia, nie wyostrza. Ruch jest wolny i miękki, nigdy sprężynujący i nigdy dla samego efektu.

## Paleta

Paleta się nie zmieniła — zmieniło się to, co wolno z nią robić. Nazwy tokenów są opisowe, używaj ich
w komentarzach i rozmowie. W stronach Aura są zmiennymi CSS w `:root`, w aplikacji Laravel —
tokenami w `resources/css/app.css`. Zmieniasz oba miejsca naraz.

### Tła — od najciemniejszego do najjaśniejszego
| Hex | Token | Rola |
| --- | --- | --- |
| `#2F2620` | `--ink` atrament | tło przycisku głównego, sekcja warsztatów, stopka, cały tekst podstawowy |
| `#EDE4D8` | `--sand-d` piasek ciemniejszy | tło sekcji wyróżnionej, przycisk wyłączony |
| `#F3EDE4` | `--sand` piasek — **tło strony** | `body` |
| `#F7F2EA` | `--linen` len | tekst na atramencie, tło pod zdjęciem, które się ładuje |
| `#FCF9F4` | `--cream` krem | karta, wypełnienie przycisku szklanego po najechaniu |
| `#fff` | biel | wyłącznie `input`, `textarea`, `select` |

### Tekst na jasnym tle
| Hex | Token | Rola |
| --- | --- | --- |
| `#2F2620` | `--ink` | nagłówki i treść główna |
| `#4A3E33` | `--graphite` | linki menu, etykiety pól |
| `#5C5043` | `--lead` | akapity prowadzące |
| `#6B5D4F` | `--help` | opisy pomocnicze |
| `#726456` | `--label` | etykiety wersalikowe, podpisy, najsłabszy tekst (4,54 na `#EDE4D8`) |

### Tekst na ciemnym tle `#2F2620` i na ciemnym szkle nad zdjęciem
| Hex | Token | Kontrast na atramencie |
| --- | --- | --- |
| `#F3EDE4` | `--on-dark-h` | 12,72 — nagłówki |
| `#D8CDBD` | `--on-dark` | 9,43 — tekst i linki |
| `#B8AB99` | `--on-dark-p` | 6,57 — akapity |
| `#9B8C7C` | `--on-dark-l` | 4,54 — etykiety wersalikowe |

Na ciemnym tle akcentem jest **róż** `#D6A39C` (6,75): kursywa w nagłówku, hover linku, kreska przed nadtytułem.

### Akcenty
| Hex | Token | Zasada |
| --- | --- | --- |
| `#855F3D` | `--brown` brąz | kursywa w nagłówku na jasnym tle, nadtytuły |
| `#24417E` | `--navy` granat | focus na jasnym tle — róż ma na piasku za mały kontrast na obrys |
| `#D6A39C` | `--rose` róż | podświetlenie przycisków po najechaniu (tekst w atramencie), licznik koszyka, znacznik „nowość”, poświata, szkliwo kubka 3D, akcent na ciemnym |
| `#B98E64` | `--dash` | kreska 34×1 px przed nadtytułem, kolor poświaty |
| `#A8813F` | złoto | drobnica, detal luksusowy |
| `#8C3A2E` | czerwień | błąd, usuwanie, wyprzedane — nigdy dekoracyjnie |
| `#5C7A4A` | zieleń | potwierdzenie („Kod gotowy”) — nigdy dekoracyjnie |

### Kolory, które istnieją tylko w efektach
Nie są kolorami tekstu ani tła treści, tylko światłem:

| Wartość | Gdzie |
| --- | --- |
| `#E6BDB5`, `#D9B98F`, `#EADFD0` | środki plam poświaty (`.orb`), zawsze w `radial-gradient` do przezroczystości |
| `#F2C39A` → `#E4A58C` | żar pieca: poświata na ekranie potwierdzenia |
| `rgba(252,249,244,.58–.6)` | `--glass` szkło jasne |
| `rgba(220,208,190,.75–.8)` | `--glass-line` obrys szkła |
| `rgba(47,38,32,.42–.45)` | `--glass-dark` szkło ciemne na zdjęciach |

Tekst na żarze to atrament (7,09 na `#E4A58C`) albo grafit (4,96). Nic jaśniejszego.

## Typografia

Dwa kroje z Google Fonts, bez zmian:

```
Newsreader: 300, 400 + kursywa 300, 400    — nagłówki, liczby, ceny, cytaty, litery na kubku 3D
Instrument Sans: 400, 500, 600             — interfejs, akapity, przyciski
```

- **Nagłówki zawsze `font-weight: 300`.** Pogrubiony Newsreader wygląda jak inna firma.
- **Skala jest większa niż w starym stylu** — Aura żyje z kontrastu wielkości:
  | Element | Rozmiar |
  | --- | --- |
  | znak słowny w hero („Mellow / *Aura*”) | `clamp(76px, 17vw, 280px)`, `line-height: .8`, `letter-spacing: -.035em` |
  | gigantyczny napis w stopce | `clamp(80px, 19.5vw, 330px)` |
  | H1 ekranu | `clamp(42px, 6.4vw, 92px)`, `line-height: .96` |
  | nagłówek sekcji | `clamp(40px, 5.6vw, 84px)`, `line-height: .98` |
  | cena na karcie produktu | `46px` Newsreader 300 |
- **Kursywa w brązie** (na ciemnym: w różu) podkreśla jedno słowo albo frazę, nie całe zdanie.
- **Nadtytuł z kreską** — sygnatura strony: `10.5px`, `letter-spacing: .3em`, wersaliki, brąz, przed nim kreska `34×1` w `#B98E64`.
- **Akapit prowadzący:** `16.5–17.5px / 1.68`, `max-width: 46ch`. Nigdy na całą szerokość.
- `text-wrap: balance` na nagłówkach, `pretty` na akapitach.
- Liczby, które się zmieniają (cena, suma, licznik), mają `font-variant-numeric: tabular-nums`, żeby nie skakały.

## Kształt

| Promień | Zastosowanie |
| --- | --- |
| `999px` | **wszystkie przyciski, znaczniki, nagłówek-pigułka, przełączniki** — bez wyjątku |
| `24–26px` | panele szklane, duże zdjęcia, scena kubka 3D, karta usługi |
| `18–20px` | karta produktu, kafel, zdjęcie w siatce |
| `12–16px` | miniatury, pola formularza, kafelki z liczbą |
| `200px 200px 16px 16px` | łuk — portret w sekcji „Cześć, jestem Kasia” |

## Głębia: szkło, obrys, cień

| Środek | Kiedy |
| --- | --- |
| obrys `1px solid #DCD0BE` | karta i pole w spoczynku |
| szkło jasne: `background: var(--glass); border: 1px solid var(--glass-line); backdrop-filter: blur(14–24px) saturate(1.3–1.4)` | nagłówek, cena na zdjęciu, panel kasy, podsumowanie, koszyk, kafle „jak powstaje”, przycisk drugoplanowy |
| szkło ciemne: `rgba(47,38,32,.42)` + obrys `rgba(247,242,234,.18)` + `blur(16–20px)` | opis na zdjęciu (karta usługi, „ten rysunek jest jeden”) |
| szkło na atramencie: `rgba(247,242,234,.06)` + obrys `rgba(247,242,234,.14)` + `blur(16px)` | kafle z liczbami w sekcji warsztatów |
| cień `0 30px 60px -34px rgba(47,38,32,.55)` | wyłącznie unoszące się zdjęcia w hero |
| cień `0 26px 50px -30px rgba(47,38,32,.5)` | karta produktu po najechaniu |

Zawsze dopisuj `-webkit-backdrop-filter` obok `backdrop-filter` — bez tego Safari na iPhonie nie rozmyje tła.
Tekst na szkle nad zdjęciem musi mieć pod sobą szkło ciemne albo przyciemnienie (`scrim`) —
jasne szkło nad jasnym zdjęciem daje nieczytelny tekst.

Gradient wolno stosować w trzech miejscach: plamy poświaty, żar pieca, przyciemnienie zdjęcia pod tekstem
(`linear-gradient` do `rgba(47,38,32,.72)`). Nigdzie indziej — żadnych gradientowych przycisków ani teł sekcji.

## Atmosfera — stała warstwa pod całą stroną

```html
<div class="aura-bg" aria-hidden="true"><div class="orb o1"></div><div class="orb o2"></div><div class="orb o3"></div></div>
<div class="grain" aria-hidden="true"></div>
```

- `.aura-bg` — `position: fixed; z-index: 0`, trzy plamy `filter: blur(90px); opacity: .55`, dryfujące
  26–32 s (`drift1–3`, `ease-in-out infinite alternate`). Treść leży na `z-index: 2`.
- `.grain` — szum z `feTurbulence` jako SVG w `data:`, `opacity: .08–.09`, `mix-blend-mode: multiply`,
  przesuwany skokowo `steps(6)`. Zawsze `pointer-events: none`.
- `.cursor-aura` — różowa poświata za kursorem, tylko przy `pointer: fine`.
- Ciemna sekcja (warsztaty) ma własne dwie plamy w środku, bo globalne jej nie przebijają.

## Gotowe komponenty

Wzorcowy kod jest w plikach Aura — kopiuj klasy stamtąd. Skrót:

**Przycisk główny z płynnym wypełnieniem** — kolor wlewa się od miejsca, w które wjechał kursor:
```css
.btn{position:relative;overflow:hidden;isolation:isolate;border-radius:999px;min-height:52px;padding:0 30px;
  background:var(--ink);color:var(--linen);--fill:var(--rose)}
.fine .btn:hover{color:var(--ink)}
.btn::before{content:"";position:absolute;z-index:-1;left:var(--mx,50%);top:var(--my,50%);width:0;aspect-ratio:1;
  border-radius:50%;transform:translate(-50%,-50%);background:var(--fill);transition:width .7s var(--ease)}
.fine .btn:hover::before{width:250%}
```
`--mx`/`--my` ustawia skrypt przy `pointerover`/`pointerout`. Bez myszy (`html:not(.fine)`) hover zmienia po prostu tło na róż.
Podświetlenie po najechaniu jest różowe, nie granatowe: róż `#D6A39C` z tekstem w atramencie (6,75). Len na różu jest nieczytelny.
Warianty `--fill`: główny → róż, szklany → krem, jasny na ciemnym → róż.

**Przycisk szklany (drugoplanowy):** `background: var(--glass); border: 1px solid var(--glass-line); backdrop-filter: blur(14px)`.

**Nagłówek-pigułka:** `position: fixed`, szkło jasne, `border-radius: 999px`, szerokość `min(1120px, 100% - 24px)`,
chowa się przy przewijaniu w dół i wraca w górę albo przy fokusie.

**Nadtytuł z kreską:**
```html
<p class="eyebrow">home studio · kraków</p>
```
```css
.eyebrow{display:flex;align-items:center;gap:12px;font-size:10.5px;letter-spacing:.3em;text-transform:uppercase;color:var(--brown)}
.eyebrow::before{content:"";width:34px;height:1px;background:var(--dash)}
```

**Karta produktu:** zdjęcie `4/5`, promień 18 px, cena w szklanej pigułce w lewym dolnym rogu, znacznik „nowość”
w różu w lewym górnym. Po najechaniu drugie zdjęcie wylewa się kołem (`clip-path: circle(0% → 75%)`)
na warstwie `.bw`. Pod zdjęciem nazwa (Newsreader 23 px) i kategoria wersalikami.

**Kafel z liczbą:** szkło, `padding: 14–20px`, liczba Newsreader 26–34 px, pod nią etykieta wersalikami.

**Pole formularza:** biel, obrys `#DCD0BE`, promień 14 px, `padding: 15–16px`, pismo 16 px,
focus: obrys atramentowy + `box-shadow: 0 0 0 4px rgba(36,65,126,.14)`.

**Kod BLIK:** sześć osobnych kratek `4/5`, Newsreader 34 px, przerwa po trzeciej. Kursor sam przeskakuje dalej,
wklejenie całego kodu wypełnia wszystkie. Wypełniona kratka lekko podskakuje (`pop`).
W aplikacji kasa ma na razie **jedno** duże pole BLIK, bo jest powiązane ze Stripe w `resources/js/checkout.js` — kratki to osobna zmiana w tym skrypcie.

**Wybór (warianty, dostawa):** pigułki z `aria-pressed` albo kafle z `role="radio"`. Wybrany = atrament z lnianym tekstem.

**Szuflada koszyka:** szkło jasne `rgba(252,249,244,.8)` + `blur(30px)`, oderwana 10 px od krawędzi, promień 26 px,
wjeżdża z prawej z rozmycia; tło strony przyciemnia się i rozmywa `blur(6px)`.

## Efekty — katalog

Każdy efekt ma jedno miejsce. Nie przenoś ich gdzie indziej bez powodu.

| Efekt | Gdzie | Jak |
| --- | --- | --- |
| **Litery z rozmycia** | znak słowny w hero, H1 karty produktu, „Dziękuję. Pakuję.” | każda litera `blurIn` w 0,7 s: od `blur(6px)` i `translateY(.2em)`, co 40 ms |
| **Rosnący kadr** | hero strony głównej | przyklejona scena `200vh`, `clip-path: inset(22% 37% round 18px)` → `inset(0)`, zdjęcia wokół odlatują i się rozmywają, na końcu wyłania się hasło |
| **Paralaksa** | zdjęcia w hero (także za kursorem), portret, karty usług, zdjęcia warsztatów | przesunięcie ±6–12 % i skala 1,12–1,35 → 1 zsynchronizowane z przewijaniem |
| **Słowa się wyostrzają** | tekst Kasi | każde słowo od `opacity .14` do pełnego, w rytmie przewijania — bez rozmycia |
| **Pasy haseł** | między sekcjami | dwa rzędy Newsreadera 10 vw jadą w przeciwne strony razem z przewijaniem; drugi rząd jako kontur |
| **Sklep w poziomie** | lista produktów na stronie głównej (≥ 768 px) | sekcja przypięta, tor przesuwa się w bok; karty przechylają się (`skewX` do ±6°) przy szybkim przewijaniu. Na telefonie zwykłe przewijanie palcem ze `scroll-snap` |
| **Kubek 3D** | konfigurator napisu | three.js: bryła obrotowa, matowy piasek z nakrapianiem, szkliwo w róż w środku i na krawędzi. Napis stemplowany litera po literze na ściance (tekstura z `<canvas>` + mapa wypukłości), każda litera lekko krzywa, kubek ugina się przy każdym stemplu. Obraca się sam, za przeciągnięciem i przy przewijaniu. Obok zawsze miniatura **prawdziwego** kubka i dopisek „podgląd poglądowy” |
| **Lot do koszyka** | „Dodaj do koszyka” na karcie produktu | kopia zdjęcia leci do licznika w nagłówku i się rozmywa, licznik podskakuje (`bump`) |
| **Przejście do produktu** | karta → strona produktu | View Transitions: zdjęcie karty rośnie w zdjęcie produktu (`view-transition-name: pvimg`), reszta rozmywa się |
| **Cena jak licznik** | zmiana wariantu | stara cena odjeżdża w rozmyciu, nowa wjeżdża z kierunku zmiany |
| **Żar potwierdzenia** | ekran po zapłacie | poświata rośnie od środka, rysuje się okrąg i ✓ (`stroke-dashoffset`) |
| **Magnes** | główne przyciski w hero i sekcjach | przycisk przesuwa się do 25 % w stronę kursora |

## Ruch — zasady

- Krzywa zawsze `cubic-bezier(.2,.7,.2,1)` (`--ease`) albo `power3.out` w GSAP. Czasy 350–1200 ms.
  Sprężyna (`elastic`) tylko przy ugięciu kubka i przy potrząśnięciu niepełnym kodem BLIK.
- **Wszystko, co ma być przeczytane, jest widoczne bez przewijania do tego miejsca.** Animacja przy wejściu
  startuje z częściowej widoczności albo działa raz (`once: true`), nigdy nie trzyma treści na `opacity: 0`
  w oczekiwaniu na zdarzenie.
- Efekt uruchamia skrypt tylko, gdy GSAP się wczytał i nie ma `prefers-reduced-motion` — wtedy dostaje
  `<html class="motion">`. Bez tej klasy strona jest statyczna i kompletna (hero zwykłej wysokości, hasło pod kadrem).
- Efekty zależne od kursora dostają dodatkowo `<html class="fine">` (tylko `pointer: fine`).
- Blok `@media (prefers-reduced-motion: reduce)` wyłącza wszystkie animacje, przejścia i animacje View Transitions.
  **Nie usuwaj go.**

### Biblioteki

| Paczka | Po co |
| --- | --- |
| `gsap` + `ScrollTrigger` | wszystko, co zależy od przewijania, przypinanie sekcji, liczniki |
| `lenis` | płynne przewijanie; `lenis.stop()` przy otwartym koszyku, menu i widoku produktu |
| `three` (r128 w plikach Aura, 0.180 w aplikacji) | wyłącznie kubek 3D (`resources/js/mug3d.js`), ładowany osobnym plikiem tylko na `/kubek-z-napisem` |

W plikach Aura biblioteki idą z cdnjs/jsdelivr. W Laravelu instalujesz je przez npm i wiążesz w Vite obok Alpine.
Innych bibliotek do animacji nie dokładaj.

## Kompozycja

- Kontener: `max-width: 1320–1360px; margin: 0 auto; padding-inline: clamp(18px, 4vw, 48px)`.
- Odstęp sekcji: `clamp(90px, 14vh, 150px)`. Aura oddycha więcej niż stary styl.
- Kolumny przez `flex-wrap: wrap` z `flex: 1 1 400–520px` i `min-width: 0` na każdym dziecku.
- Zapytania medialne tylko tam, gdzie zmienia się zachowanie, nie tylko układ: hero na telefonie
  (inny kadr, mniej zdjęć), sklep w poziomie od 768 px, menu zamiast linków poniżej 920 px, przypięty pasek zakupu poniżej 760 px.
- Rytm sekcji: nadtytuł → wielki nagłówek z kursywą → akapit prowadzący → przyciski → kafle z liczbami.

## Dostępność — to nie jest opcja

- Focus: `outline: 2px solid #24417E; outline-offset: 3px`. Na ciemnym tle, na ciemnym szkle i w hasłach
  nad zdjęciem — len `#F7F2EA` (klasa `.on-dark` na rodzicu w plikach Aura, `focus-on-dark` w aplikacji).
- Tekst na szkle nad zdjęciem: szkło ciemne albo przyciemnienie pod spodem. Tekst na jasnym szkle nad piaskiem:
  `--label` daje 5,22 — przechodzi.
- Przypięte i poziome sekcje muszą działać z klawiatury: fokus na karcie poza ekranem przewija stronę tak,
  żeby ją pokazać; fokus na haśle w hero przewija do końca animacji.
- Koszyk, menu i widok produktu: `Escape` zamyka, fokus wraca tam, skąd przyszedł.
- Kubek 3D ma `role="img"` z `aria-label` zawierającym aktualny napis. Gdy WebGL nie działa, pokazuje się zdjęcie prawdziwego kubka.
- Dekoracje (plamy, ziarno, pływające zdjęcia, pasy haseł, gigantyczny napis w stopce) mają `aria-hidden="true"`.
- Każdy nowy kolor tekstu przepuść przez `kontrast.py` (skill `mellowaura-dostepnosc`).

## Wydajność

- `backdrop-filter` jest drogi. Jednocześnie na ekranie najwyżej kilka szklanych warstw; nie nakładaj szkła na szkło.
- Scena 3D renderuje się tylko, gdy jest widoczna (`IntersectionObserver`), `pixelRatio` najwyżej 2.
- Przed wdrożeniem sprawdź płynność na telefonie Kasi — jeśli rozmycia klatkują, najpierw zmniejsz `blur` plam, potem liczbę szklanych warstw.

## Czego nie robić

| Nie | Dlaczego |
| --- | --- |
| Białe tło `#fff` na płaszczyźnie | zimne, wypada ze świata ceramiki |
| Gradient na przycisku albo tle sekcji | gradient jest tylko światłem: poświata, żar, przyciemnienie zdjęcia |
| Szkło na szkle, jasne szkło z tekstem nad jasnym zdjęciem | nieczytelne i wolne |
| Pogrubiony nagłówek szeryfowy | traci charakter pisma odręcznego |
| Nowy odcień „bo pasuje” | odpowiedź jest w palecie |
| Przycisk z rogiem innym niż `999px` | rozpada się spójność |
| Efekt spoza katalogu albo efekt w innym miejscu niż w katalogu | Aura działa, bo każdy efekt coś znaczy |
| Plansza ładowania, licznik 0→100 %, falowanie zdjęć pod kursorem | Maciej usunął je 23.09.2026 — spowalniały wejście i rozpraszały |
| Animacja, która chowa treść do czasu przewinięcia | pierwszy kadr, miniatura i czytnik ekranu muszą widzieć całość |
| Wymyślone liczby i opinie | fakty przychodzą z panelu i od Kasi |
| Ikony z zewnętrznej biblioteki | wystarczą `→`, `·`, `×`, `+` |

## Dokąd dalej

Ten skill mówi, **jak to ma wyglądać i się ruszać**. O tym, czy klient dojdzie do końca, mówią
`mellowaura-ux` (ścieżki i formularze), `mellowaura-teksty` (co jest napisane na przycisku),
`mellowaura-dostepnosc` (kontrast, klawiatura, ruch) i `mellowaura-aplikacja` (telefon).
Materiały do druku (`mellowaura-druk`) zostają płaskie: bez szkła, rozmyć i ziarna — papier ich nie potrzebuje.
