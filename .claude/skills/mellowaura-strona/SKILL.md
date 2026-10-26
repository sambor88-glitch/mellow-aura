---
name: mellowaura-strona
description: Dodawanie i przebudowa widoków strony MellowAura w pliku MellowAura.dc.html — routing, stan, adres URL, dane do Google, nawigacja, gotowe układy sekcji. Użyj, gdy pojawia się prośba o nową podstronę, zakładkę, sekcję na stronie głównej, landing pod kampanię albo o zmianę tego, co klient widzi w sklepie.
---

# Nowy widok na stronie MellowAura

Cała strona to jeden plik `MellowAura.dc.html` — 20 widoków, jeden stan, jeden komponent.
Ten plik rozstrzyga o treści, adresach i przebiegu widoków. Wygląd i ruch od 22.09.2026 bierzesz
z kierunku „Aura” (`Aura - strona glowna.html`, `Aura - ekrany.html`, skill `mellowaura-design`).
Nowy widok wymaga zmian w **pięciu miejscach**. Pominięcie któregokolwiek daje ekran,
który działa w klikaniu, ale znika po odświeżeniu albo jest niewidoczny dla Google.

## Jak plik jest zbudowany

| Zakres linii | Co tam jest |
| --- | --- |
| `<helmet>` na górze | czcionki, style globalne, animacje |
| markup do ~linii 2010 | wszystkie widoki, każdy owinięty w `<sc-if>` |
| `<script type="text/x-dc">` od ~2013 | dane, `ROUTES`, klasa `Component`, `renderVals()` |

Składnia to nie React. Obowiązują trzy znaczniki:

- `{{ nazwa }}` — wartość z `renderVals()`
- `<sc-if value="{{ warunek }}" hint-placeholder-val="{{ true }}">` — warunek
- `<sc-for list="{{ lista }}" as="x" hint-placeholder-count="3">` — pętla, w środku `{{ x.pole }}`

Atrybuty `hint-placeholder-*` służą podglądowi w edytorze. Zawsze je dopisuj.

## Pięć kroków — kolejność ma znaczenie

### 1. Adres i dane dla Google — `ROUTES`

```js
prezenty_firmowe: {
  path: '/prezenty-firmowe',
  title: 'Prezenty firmowe z ceramiki — Kraków | MellowAura',
  desc: 'Zdanie do 155 znaków, po polsku, z korzyścią i miastem. To jest tekst, który ludzie zobaczą w Google.'
},
```

Klucz obiektu to nazwa widoku w stanie. `path` po polsku, małymi literami, z myślnikami, bez ogonków —
`/prezenty-firmowe`, nie `/prezentyFirmowe`. Funkcja `seo()` sama podmieni `<title>` i `og:`.

### 2. Przełącznik i nawigacja — `renderVals()`

Obok pozostałych flag, przy `isAdmin`:

```js
isCorpGifts: s.view === 'prezenty_firmowe',
goCorpGifts: nv('prezenty_firmowe'),
```

Jeśli widok ma być w menu, wpisz `goCorpGifts` również do obiektu `nav` — tam trzymane są wszystkie
przejścia i dzięki temu `go()` przewija stronę na górę oraz zamyka koszyk.

### 3. Markup widoku

Wstaw obok innych widoków, przed zamknięciem kontenera treści:

```html
<sc-if value="{{ isCorpGifts }}" hint-placeholder-val="{{ false }}">
<div style="animation: maIn .4s ease both">
  <div style="max-width: 1280px; margin: 0 auto; padding: 0 28px">
    <!-- sekcje -->
  </div>
</div>
</sc-if>
```

`animation: maIn .4s ease both` na korzeniu widoku jest obowiązkowe — bez niego przejście szarpie.

### 4. Link w nagłówku

W `<nav>` na górze pliku:

```html
<a href="#/prezenty-firmowe" onClick="{{ goCorpGifts }}" style="color: #4A3E33" style-hover="color: #24417E">Prezenty firmowe</a>
```

`href` **musi** być identyczny z `path` z `ROUTES`, bo po tym polu `onHash()` odnajduje widok
przy wejściu z linku albo po odświeżeniu.

### 5. Stopka i linkowanie wewnętrzne

Dorzuć odnośnik w stopce i z co najmniej jednego pokrewnego widoku. Strona bez linków
przychodzących nie istnieje dla wyszukiwarki.

## Sprawdzenie przed oddaniem

1. Kliknięcie w menu otwiera widok, adres w pasku się zmienia.
2. **Odświeżenie na tym adresie wraca do tego samego widoku** — to najczęstszy błąd.
3. Przycisk „wstecz" w przeglądarce działa.
4. Zakładka przeglądarki pokazuje tytuł z `ROUTES`, nie tytuł strony głównej.
5. Przy szerokości 390 px nic nie wystaje w bok.
6. Koszyk w nagłówku nadal liczy sztuki.

## Układy sekcji — sprawdzone w tym projekcie

**Nagłówek widoku** — otwiera każdą podstronę
```html
<div style="padding: 72px 0 40px">
  <div style="display: flex; align-items: center; gap: 12px; margin-bottom: 26px">
    <span style="width: 34px; height: 1px; background: #B98E64"></span>
    <span style="font-size: 10.5px; letter-spacing: 0.3em; text-transform: uppercase; color: #855F3D">nadtytuł</span>
  </div>
  <h1 style="font-family: Newsreader, serif; font-weight: 300; font-size: clamp(32px, 4.4vw, 52px); line-height: 1.04; margin: 0 0 20px; text-wrap: pretty">Nagłówek</h1>
  <p style="font-size: 17.5px; line-height: 1.68; color: #5C5043; max-width: 46ch; margin: 0; text-wrap: pretty">Jedno zdanie o tym, po co tu jesteś.</p>
</div>
```

**Dwie kolumny: treść i zdjęcie** — same się układają na telefonie
```html
<div style="display: flex; flex-wrap: wrap; gap: 56px; align-items: center; padding: 72px 0 84px">
  <div style="flex: 1 1 400px; min-width: 0"><!-- treść --></div>
  <div style="flex: 1 1 380px; min-width: 0"><!-- zdjęcie --></div>
</div>
```

**Siatka kart**
```html
<div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 26px">
  <sc-for list="{{ pozycje }}" as="it" hint-placeholder-count="6">
    <div style="background: #FCF9F4; border: 1px solid #DCD0BE; border-radius: 4px; overflow: hidden">
      <div style="aspect-ratio: 4/5; background: #E6DCCD">
        <img src="{{ it.img }}" alt="{{ it.alt }}" style="width: 100%; height: 100%; object-fit: cover; display: block" />
      </div>
      <div style="padding: 18px">
        <div style="font-family: Newsreader, serif; font-size: 20px">{{ it.name }}</div>
        <div style="font-size: 13px; color: #726456; margin-top: 4px">{{ it.priceLabel }}</div>
      </div>
    </div>
  </sc-for>
</div>
```

**Pas z wezwaniem do działania** — jedyne miejsce z ciemnym tłem na pełną szerokość
```html
<div style="background: #2F2620; color: #F7F2EA; padding: 64px 28px; margin-top: 80px">
  <div style="max-width: 720px; margin: 0 auto; text-align: center">
    <div style="font-family: Newsreader, serif; font-weight: 300; font-size: clamp(28px, 3.6vw, 40px); line-height: 1.1; margin-bottom: 18px">Zdanie, które prosi o decyzję.</div>
    <button onClick="{{ akcja }}" style="background: #F7F2EA; color: #2F2620; border: 0; border-radius: 999px; padding: 16px 32px; font-size: 14px; letter-spacing: 0.04em" style-hover="background: #D6A39C">Zrób to</button>
  </div>
</div>
```

## Widoki z parametrem w adresie

`/produkt/:id` i `/dziennik/:slug` mają własną obsługę w `onHash()` — dopasowanie po początku ścieżki,
nie po pełnym dopasowaniu. Jeśli nowy widok też potrzebuje identyfikatora w adresie, dopisz analogiczny
blok **przed** ogólnym wyszukiwaniem `Object.keys(ROUTES).find(...)`, bo ono dopasowuje ścieżkę w całości.

## Tekst na stronie

Piszesz głosem właścicielki: pierwsza osoba, krótkie zdania, konkret zamiast obietnicy.
„Litery wbijam stemplem jedna po drugiej w surową glinę" zamiast „personalizowany napis".
Ceny zawsze z formatem `zł` przez istniejącą funkcję `zl()`, nigdy ręcznie sklejane.
