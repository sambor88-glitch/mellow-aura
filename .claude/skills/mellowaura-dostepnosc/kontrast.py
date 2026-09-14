#!/usr/bin/env python3
"""Kontrast WCAG dla palety MellowAury.

Uzycie:
    python3 kontrast.py                  tabela calej palety na wszystkich tlach
    python3 kontrast.py '#8A7A69'        jeden kolor na wszystkich tlach
    python3 kontrast.py '#8A7A69' '#F3EDE4'   jedna para
    python3 kontrast.py --fix '#A0907D'  najjasniejszy odcien tego samego tonu, ktory przechodzi AA

Progi WCAG 2.1:
    4.5:1  tekst zwykly (AA)
    3.0:1  tekst duzy: >=24px, albo >=19px pogrubiony (AA)
    7.0:1  tekst zwykly (AAA)
"""
import sys
import colorsys

TLA = {
    '#F3EDE4': 'piasek — tlo strony',
    '#EDE4D8': 'piasek ciemny — panel',
    '#FCF9F4': 'krem — karta',
    '#F7F2EA': 'len',
    '#2F2620': 'atrament',
}

TEKST = ['#2F2620', '#4A3E33', '#5C5043', '#6B5D4F', '#8A7A69', '#A0907D',
         '#8C6440', '#24417E', '#D6A39C', '#A8813F', '#8C3A2E', '#F7F2EA']


def _lin(c):
    c /= 255
    return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4


def luminancja(h):
    h = h.lstrip('#')
    r, g, b = (int(h[i:i + 2], 16) for i in (0, 2, 4))
    return 0.2126 * _lin(r) + 0.7152 * _lin(g) + 0.0722 * _lin(b)


def kontrast(a, b):
    la, lb = luminancja(a), luminancja(b)
    hi, lo = max(la, lb), min(la, lb)
    return (hi + 0.05) / (lo + 0.05)


def ocena(r):
    if r >= 7:
        return 'AAA'
    if r >= 4.5:
        return 'AA '
    if r >= 3:
        return 'AA+'   # tylko tekst duzy
    return 'ZLE'


def popraw(src, cel=4.5, tlo='#EDE4D8'):
    """Najjasniejszy odcien o tym samym tonie i nasyceniu, ktory osiaga `cel` na `tlo`."""
    h = src.lstrip('#')
    r, g, b = (int(h[i:i + 2], 16) / 255 for i in (0, 2, 4))
    ton, jasnosc, nasyc = colorsys.rgb_to_hls(r, g, b)
    while jasnosc > 0:
        rr, gg, bb = colorsys.hls_to_rgb(ton, jasnosc, nasyc)
        kand = '#%02X%02X%02X' % (round(rr * 255), round(gg * 255), round(bb * 255))
        if kontrast(kand, tlo) >= cel:
            return kand
        jasnosc -= 0.002
    return '#000000'


def tabela(kolory):
    print(f"{'kolor':10}", '  '.join(f'{b:>9}' for b in TLA))
    for t in kolory:
        kom = '  '.join(f'{kontrast(t, bg):5.2f} {ocena(kontrast(t, bg))}' for bg in TLA)
        print(f'{t:10}', kom)
    print('\nAAA 7:1  |  AA 4.5:1 tekst zwykly  |  AA+ 3:1 tylko tekst >=24px  |  ZLE ponizej 3:1')


if __name__ == '__main__':
    a = sys.argv[1:]
    if a and a[0] == '--fix':
        src = a[1]
        naprawiony = popraw(src)
        print(f'{src} -> {naprawiony}')
        tabela([src, naprawiony])
    elif len(a) == 2:
        r = kontrast(a[0], a[1])
        print(f'{a[0]} na {a[1]}: {r:.2f}:1  {ocena(r)}')
    elif len(a) == 1:
        tabela([a[0]])
    else:
        tabela(TEKST)
