---
name: mellowaura-panel
description: Ekrany panelu właścicielki i CRM — zamówienia, klienci, rezerwacje warsztatów, stany magazynowe, cenniki, kafelki z liczbami, tabele, filtry, statusy, formularze i stany puste. Użyj przy każdej prośbie o „panel", „CRM", „zarządzanie zamówieniami", „lista klientów", „zaplecze", „raport", „statystyki", „coś do klikania dla mnie, nie dla klientów".
---

# Panel właścicielki i CRM

Panel to nie ta sama strona co sklep — inaczej wygląda i inaczej się zachowuje. Ma jednego użytkownika,
który jest w nim codziennie. Liczy się szybkość dojścia do informacji, nie wrażenie.

## Cztery zasady, które odróżniają panel od sklepu

1. **Tło `#EDE4D8`, nie `#F3EDE4`.** Ciemniejszy piasek mówi „jesteś na zapleczu". Karty na nim to `#FCF9F4`.
2. **Gęściej.** Odstęp sekcji `44px / 96px`, wewnątrz kart `28px 26px`. Sklep oddycha, panel pracuje.
3. **Każdy ekran mówi, co się stanie po zapisaniu.** Kolumna podglądu „Tak to zobaczą klienci" obok
   formularza to wzorzec z tego projektu — trzymaj go wszędzie, gdzie zmiana jest widoczna publicznie.
4. **Język bez żargonu.** „Wyprzedane", nie „status: out_of_stock". „Dodaj produkt", nie „Utwórz encję".

## Wejście do panelu

Panel jest za logowaniem (`isAuthed` / `notAuthed`). W prototypie hasło jest dowolne.
Ekran logowania to jedna karta `max-width: 420px` i zdanie mówiące wprost, że to miejsce
tylko dla właścicielki. Nie dokładaj rejestracji ani odzyskiwania hasła — nie ma tu innych kont.

**Do wdrożenia, napisz o tym wprost, gdy ktoś pyta o produkcję:** prawdziwe logowanie i dwa poziomy
uprawnień — właścicielka (wszystko) oraz osoba pomagająca przy zamówieniach, bez wglądu w ceny zakupu
i wypłaty.

## Pasek zakładek — kręgosłup panelu

Zakładki generuje `adminTabs` w `renderVals()`. Nową zakładkę dopisujesz do tablicy, kolory liczą się same:

```js
adminTabs: [
  { label: 'Produkty', id: 'produkty' },
  { label: 'Zamówienia', id: 'zamowienia' },
].map(t => ({ label: t.label,
  bg: s.adminTab === t.id ? '#2F2620' : 'transparent',
  fg: s.adminTab === t.id ? '#F7F2EA' : '#5C5043',
  bd: s.adminTab === t.id ? '#2F2620' : '#C6B8A5',
  set: () => this.setState({ adminTab: t.id }) })),
```

Do markupu dochodzi flaga `tabZam: s.adminTab === 'zamowienia'` i owinięcie zawartości w `<sc-if>`.
Przycisk „Wyloguj" zostaje na końcu paska z `margin-left: auto`.

Powyżej sześciu zakładek pasek się łamie i przestaje być czytelny — wtedy zamiast siódmej
rozbuduj istniejącą albo zaproponuj boczne menu.

## Kafelek z liczbą

Panel otwiera rząd czterech liczb: dziś, ten tydzień, do wysłania, do odpisania.

```html
<div style="display: flex; flex-wrap: wrap; gap: 16px; margin-bottom: 30px">
  <sc-for list="{{ kpi }}" as="k" hint-placeholder-count="4">
    <div style="flex: 1 1 180px; min-width: 0; background: #FCF9F4; border: 1px solid #DCD0BE; border-radius: 4px; padding: 20px 22px">
      <div style="font-family: Newsreader, serif; font-size: 34px; line-height: 1; color: #2F2620">{{ k.value }}</div>
      <div style="font-size: 11.5px; letter-spacing: 0.14em; text-transform: uppercase; color: #8A7A69; margin-top: 6px">{{ k.label }}</div>
      <div style="font-size: 12.5px; color: #A0907D; margin-top: 8px">{{ k.hint }}</div>
    </div>
  </sc-for>
</div>
```

Liczba zawsze Newsreaderem. Cztery kafelki maksimum — piąty i szósty nikt już nie czyta.
Każdy kafelek odpowiada na pytanie, które właścicielka faktycznie sobie zadaje rano.

## Lista zamówień

W tym projekcie **nie używamy `<table>`**. Wiersz to karta z siatką, która na telefonie
składa się w jedną kolumnę:

```html
<div style="display: grid; gap: 10px">
  <sc-for list="{{ zamowienia }}" as="z" hint-placeholder-count="5">
    <div style="background: #FCF9F4; border: 1px solid #DCD0BE; border-radius: 4px; padding: 16px 18px; display: flex; flex-wrap: wrap; gap: 14px; align-items: center">
      <div style="flex: 0 0 auto; font-size: 12.5px; color: #A0907D; font-variant-numeric: tabular-nums">{{ z.nr }}</div>
      <div style="flex: 1 1 200px; min-width: 0">
        <div style="font-size: 14.5px; color: #2F2620">{{ z.klient }}</div>
        <div style="font-size: 12.5px; color: #8A7A69; margin-top: 2px">{{ z.pozycje }}</div>
      </div>
      <div style="flex: 0 0 auto; font-family: Newsreader, serif; font-size: 19px; font-variant-numeric: tabular-nums">{{ z.kwota }}</div>
      <span style="flex: 0 0 auto; border-radius: 999px; padding: 5px 12px; font-size: 11.5px; background: {{ z.stBg }}; color: {{ z.stFg }}">{{ z.status }}</span>
      <button onClick="{{ z.dalej }}" style="flex: 0 0 auto; background: transparent; border: 1px solid #C6B8A5; border-radius: 999px; padding: 8px 16px; font-size: 12.5px; color: #2F2620" style-hover="border-color: #2F2620; background: #EDE4D8">{{ z.akcja }}</button>
    </div>
  </sc-for>
</div>
```

`font-variant-numeric: tabular-nums` przy każdej kwocie i numerze — cyfry ustawiają się w kolumnę.

## Statusy — pięć, nie więcej

| Status | Tło | Tekst |
| --- | --- | --- |
| Nowe | `#D6A39C` | `#2F2620` |
| W realizacji | `#EDE4D8` | `#5C5043` |
| Wysłane | `#F0F3FA` | `#24417E` |
| Zakończone | `#F7F2EA` | `#8A7A69` |
| Problem | `#F7ECE9` | `#8C3A2E` |

Status ma być rzeczownikiem po polsku i znaczyć dokładnie jeden etap. Jeśli potrzebujesz szóstego,
najpierw sprawdź, czy dwa istniejące nie znaczą tego samego.

## Formularz

Wzorzec z zakładki „Produkty": karta z formularzem po lewej (`flex: 1 1 340px`), kolumna podglądu
po prawej (`flex: 0 1 260px`). Reguły:

- Pola w `display: grid; gap: 13px` — jedna kolumna, jedno pod drugim. Dwa pola w rzędzie tylko
  wtedy, gdy naprawdę są parą (rozmiar i cena).
- Wgrywanie zdjęcia to `<label>` z linią przerywaną `1px dashed #C6B8A5` i ukrytym `<input type="file">`.
  Po wybraniu pliku pokazujesz podgląd nad opisem.
- Podpowiedź pod polem: `12.5px`, kolor `#A0907D`, zdanie po ludzku — „JPG lub PNG, najlepiej kwadrat".
- Wiersze powtarzalne (rozmiary, terminy) mają przycisk `+ dodaj` z linią przerywaną i `×` do usunięcia,
  aktywne dopiero od drugiego wiersza.
- Przycisk zapisu na końcu, pełna szerokość, `padding: 16px`, tekst mówiący co się stanie:
  „Opublikuj w sklepie", nie „Zapisz".
- Liczby czyść przy wpisywaniu: `e.target.value.replace(/[^\d,.]/g, '')`.

## Potwierdzenie zapisu

Po każdej zmianie pokaż pigułkę na dole ekranu — wzorzec `hasToast` już jest w pliku:

```js
this.setState({ toast: 'Zapisane. Klienci już to widzą.' });
setTimeout(() => this.setState({ toast: '' }), 2600);
```

Treść mówi o skutku, nie o operacji. „Termin dodany — widać go na stronie warsztatów".

## Stan pusty

Nigdy nie zostawiaj gołej pustki. Karta z linią przerywaną, jedno zdanie i przycisk:

```html
<div style="border: 1px dashed #C6B8A5; border-radius: 4px; padding: 40px 28px; text-align: center; background: #F7F2EA">
  <div style="font-family: Newsreader, serif; font-size: 22px; margin-bottom: 8px">Jeszcze nic tu nie ma</div>
  <p style="font-size: 13.5px; color: #8A7A69; margin: 0 0 20px; max-width: 42ch; margin-inline: auto">Pierwsze zamówienie pojawi się tutaj razem z adresem do wysyłki i sposobem płatności.</p>
  <button onClick="{{ akcja }}" style="background: #2F2620; color: #F7F2EA; border: 0; border-radius: 999px; padding: 13px 26px; font-size: 13.5px" style-hover="background: #24417E">Dodaj pierwszy produkt</button>
</div>
```

## Usuwanie

Usuwanie jest nieodwracalne, więc nigdy nie wieszaj go na pojedynczym kliknięciu bez pytania.
Krzyżyk `×` w kolorze `#B0A190`, po najechaniu `#8C3A2E`, a potem pytanie w tym samym miejscu
(„Na pewno? Tak / Anuluj"), nie okno systemowe.

Zamiast kasowania produktu preferuj ukrycie — w stanie jest już `hidden: {}`. Ukryty produkt znika
ze sklepu, ale zostaje w historii zamówień. To ratuje dane do księgowości.

## Czego pilnować przy danych klientów

Panel operuje na adresach i telefonach. Trzy rzeczy do powiedzenia właścicielce wprost, gdy temat wraca:

- W prototypie dane żyją w pamięci przeglądarki i znikają po odświeżeniu — nic nie wycieka, ale też nic nie zostaje.
- Przy wdrożeniu dane osobowe idą do bazy po stronie serwera, nie do pliku w repozytorium.
- **Do repozytorium nigdy nie trafiają zrzuty ekranu z panelu z prawdziwymi zamówieniami.**
  Katalog `uploads/` jest w `.gitignore` właśnie po to.
