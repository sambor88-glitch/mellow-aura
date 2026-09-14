---
name: mellowaura-zdjecia
description: Zdjęcia produktów i pracowni — kadrowanie do proporcji używanych na stronie, format i waga pliku, nazewnictwo, opisy alternatywne po polsku, galeria produktu, zdjęcia do social mediów. Użyj przy prośbie o „dodanie zdjęć", „przygotowanie fotek", „strona długo się ładuje", „zdjęcia są za duże", „jak fotografować produkty".
---

# Zdjęcia w MellowAurze

Sklep z ceramiką sprzedaje zdjęciem. Klient nie weźmie kubka do ręki, więc zdjęcie musi pokazać
grubość ścianki, ślad palca w glinie i prawdziwy kolor szkliwa.

## Stan na dziś — i co z tym zrobić

Katalog `zdjecia/` ma 16 plików **PNG** o łącznej wadze **około 14 MB**.
Najcięższy, `zestaw-flatlay.png`, to 2 MB przy wymiarach 867 × 920 px.

To jest realny koszt: na telefonie w zasięgu LTE strona główna wciąga kilka megabajtów zanim
cokolwiek się pokaże, a część osób wychodzi wcześniej.

**PNG to zły format dla fotografii.** PNG powstał do grafik z płaskimi kolorami i przezroczystością.
Zdjęcie ceramiki to tysiące odcieni — kompresja PNG nie ma na nich czego oszczędzić.

Przewalutowanie na WebP przy tej samej jakości daje **85–90% oszczędności**:

```bash
# jeden plik
cwebp -q 82 zdjecia/wazon-rzezbiony.png -o zdjecia/wazon-rzezbiony.webp

# cały katalog
for f in zdjecia/*.png; do cwebp -q 82 "$f" -o "${f%.png}.webp"; done
```

Jakość `82` jest granicą, poniżej której na szkliwie widać pasy. Po przewalutowaniu podmieniasz
rozszerzenia w polach `img` i `gallery` w tablicy `PRODUCTS` oraz w `studioHero`.

**Cel:** każde zdjęcie produktu poniżej **150 KB**, zdjęcie na całą szerokość poniżej **250 KB**.

## Wymiary

| Zastosowanie | Proporcja | Zalecany rozmiar |
| --- | --- | --- |
| Kafelek w sklepie | `4/5` pionowo | 1000 × 1250 px |
| Zdjęcie główne produktu | `4/5` pionowo | 1400 × 1750 px |
| Miniatura galerii | `1/1` kwadrat | 400 × 400 px |
| Zdjęcie szerokie (pracownia, nagłówek) | `16/9` | 1920 × 1080 px |
| Post na Instagram | `4/5` | 1080 × 1350 px |

Strona kadruje przez `object-fit: cover`, więc zdjęcie w innej proporcji nie zepsuje układu —
ale przytnie brzegi. **Zostaw 10% zapasu wokół przedmiotu**, żeby kadrowanie nie obcięło ucha kubka.

Obecne pliki mają 500–900 px. To wystarcza na kafelek, ale przy powiększeniu na pełny ekran
widać miękkość. Nowe zdjęcia rób w większej rozdzielczości.

## Nazewnictwo

Wzorzec z katalogu, trzymaj się go: `przedmiot-cecha.webp`, małymi literami, myślniki,
**bez polskich znaków**.

```
kubek-krolowa-matka.webp      dobrze
talerz-niebieski-odcisk.webp  dobrze
IMG_4821.png                  źle — nic nie mówi
Kubek Królowa Matka.png       źle — spacje i ogonki psują adresy
```

Zdjęcia tego samego produktu numeruj: `wazon-rzezbiony-1.webp`, `-2`, `-3`.
Nazwa pliku trafia do Google Grafiki — `kubek-z-napisem-krolowa-matka` znajdzie więcej osób niż `img-07`.

## Opis alternatywny

Każde `<img>` ma `alt` po polsku. To nie jest formalność: czyta go czytnik ekranu osoby niewidomej,
wyświetla się, gdy zdjęcie się nie wczyta, i trafia do wyszukiwarki.

```html
<img src="zdjecia/kubek-krolowa-matka.webp"
     alt="Piaskowy kubek z wbitym stemplem napisem „królowa matka", wnętrze szkliwione na turkus" />
```

- Opisz **przedmiot**, nie plik: co to jest, jaki kolor, jaki detal.
- 8–15 słów. Dłuższy opis czytnik przerywa.
- Nie zaczynaj od „zdjęcie przedstawiające" — czytnik już powiedział, że to obraz.
- Zdjęcie czysto dekoracyjne dostaje `alt=""`, nie brak atrybutu.

## Galeria produktu

W tablicy `PRODUCTS` pole `gallery` to lista ścieżek. Pierwsza pozycja to zawsze to samo zdjęcie,
co w `img` — inaczej kafelek w sklepie pokazuje co innego niż otwarta karta produktu.

Kolejność, która sprzedaje:
1. Przedmiot w całości, na jednolitym tle, w prawdziwym kolorze.
2. Detal — ślad dłoni, odcisk rośliny, krawędź szkliwa.
3. W użyciu — kubek z kawą, talerz na stole, opaska na włosach.
4. Skala — obok czegoś znanego, żeby było widać wielkość.

Cztery zdjęcia wystarczą. Ósme nikt nie ogląda, a każde kosztuje czas wczytywania.

## Wczytywanie

- Zdjęcia poniżej pierwszego ekranu: `loading="lazy"`.
- Zdjęcie główne na stronie głównej: **bez** `lazy` — ma być od razu.
- Zawsze tło pod zdjęciem: `background: #E6DCCD`. Zanim plik dojdzie, jest piaskowy prostokąt,
  nie biała dziura.
- `width` i `height` albo `aspect-ratio` na elemencie — bez tego strona podskakuje przy wczytywaniu.

## Jak fotografować — ściąga dla pracowni

- **Światło dzienne z boku, okno po lewej lub prawej.** Nigdy lampa błyskowa — spłaszcza szkliwo
  i zabija połysk, który jest tu wartością.
- Tło: len, płótno, surowa deska w odcieniu `#F3EDE4` lub `#EDE4D8`. Białe tło z tej marki nie pasuje.
- Ta sama pora dnia i to samo miejsce dla całej serii — inaczej w siatce sklepu jeden kubek jest ciepły,
  drugi zimny i całość wygląda przypadkowo.
- Balans bieli ustaw raz i nie ruszaj. Turkusowe szkliwo ma wyjść turkusowe, bo klient będzie
  reklamował kolor, nie zdjęcie.
- Telefon wystarczy. Statyw i cierpliwość dają więcej niż droższy aparat z ręki.

## Czego nie wrzucamy do repozytorium

Katalog `uploads/` jest w `.gitignore` i ma tam zostać. Trafiają do niego surowe zrzuty ekranu,
na których mogą być dane klientów albo klucze dostępowe. **Repozytorium jest publiczne** —
raz wgrany plik zostaje w historii nawet po usunięciu.

Do `zdjecia/` wrzucasz wyłącznie gotowe, przycięte zdjęcia produktów i pracowni.
