---
name: mellowaura-zdjecia
description: Zdjęcia produktów i pracowni — kadrowanie do proporcji używanych na stronie, format i waga pliku, nazewnictwo, opisy alternatywne po polsku, galeria produktu, zdjęcia do social mediów. Użyj przy prośbie o „dodanie zdjęć", „przygotowanie fotek", „strona długo się ładuje", „zdjęcia są za duże", „jak fotografować produkty".
---

# Zdjęcia w MellowAurze

Sklep z ceramiką sprzedaje zdjęciem. Klient nie weźmie kubka do ręki, więc zdjęcie musi pokazać
grubość ścianki, ślad palca w glinie i prawdziwy kolor szkliwa.

## Format: WebP, i tak ma zostać

Katalog `zdjecia/` to 16 plików **WebP** o łącznej wadze **1,2 MB**. Wcześniej były to PNG-i
ważące 14,2 MB — konwersja ścięła 92%.

**Nie wracaj do PNG dla fotografii.** PNG powstał do grafik z płaskimi kolorami i przezroczystością;
zdjęcie szkliwa to tysiące odcieni, na których jego kompresja bezstratna nie ma czego oszczędzać.
Jedno zdjęcie potrafiło ważyć 2 MB tam, gdzie wystarczy 100 KB.

### Dokładanie nowego zdjęcia

Zdjęcie z aparatu albo telefonu przewalutuj przed wrzuceniem. Jeśli w systemie jest `cwebp`:

```bash
cwebp -q 85 -m 6 nowe-zdjecie.png -o zdjecia/nowe-zdjecie.webp
```

Jeśli nie ma, wystarczy Pillow (`pip install Pillow`):

```python
from PIL import Image
im = Image.open('nowe-zdjecie.png').convert('RGB')   # alfa w tych zdjęciach jest nieużywana
im.save('zdjecia/nowe-zdjecie.webp', 'WEBP', quality=85, method=6)
```

**Nie ustawiaj jakości na sztywno dla całej serii.** Przy stałej wartości 82 cztery zdjęcia
z tego katalogu schodziły poniżej progu, od którego na gładkim szkliwie widać pasy. Zamiast tego
dobieraj jakość per plik tak, żeby PSNR wobec oryginału wyniósł **co najmniej 40 dB** — dla tych
16 zdjęć wyszły wartości od 70 do 93. Skrypt liczący PSNR to kilkanaście linii z Pillow
(`ImageChops.difference` i histogram), a różnica w wadze całego katalogu to niecałe 270 KB.

Po wrzuceniu pliku dopisz ścieżkę w polach `img` i `gallery` w tablicy `PRODUCTS`, a dla zdjęć
pracowni w `studioHero`. W prototypie Kasia może też wgrać zdjęcia z panelu (Produkty → pasek galerii przy produkcie,
kilka plików naraz) — trafiają do `s.gals[id]` i żyją do odświeżenia strony; we wdrożeniu idą na serwer.

**Cel wagowy:** zdjęcie produktu poniżej **150 KB**, zdjęcie na całą szerokość poniżej **250 KB**.
Jeden plik ten próg przekracza — `zestaw-flatlay.webp` ma 313 KB, bo to flat-lay gęsty od drobnych
faktur i zejście niżej kosztowałoby widoczną jakość. To świadomy wyjątek, nie niedopatrzenie.

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
