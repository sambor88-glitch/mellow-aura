#!/usr/bin/env bash
# Rebuilds the site's web fonts (MA-271): one woff2 per weight and style instead of a `latin` and a `latin-ext` file.
# Source: the static instances Google Fonts serves for Instrument Sans and Newsreader (the same outlines Bunny served
# before — checked glyph by glyph). Needs fontTools with brotli: `pip install fonttools brotli`.
#
# Characters: Google's `latin` range plus the eighteen Polish letters. Everything the site's texts use is in there
# („ ” – — … × · € and the rest of Latin-1); the arrows and other symbols never came from these files and still fall
# back to the system font, so nothing changes on screen. A letter outside the range (ř, ő) falls back the same way.
# Only the seven variants the pages render are built: Instrument Sans 400/500/600 and Newsreader 300/400 upright and italic.
set -euo pipefail

cd "$(dirname "$0")"

LATIN='U+0000-00FF,U+0131,U+0152-0153,U+02BB-02BC,U+02C6,U+02DA,U+02DC,U+0304,U+0308,U+0329,U+2000-206F,U+20AC,U+2122,U+2191,U+2193,U+2212,U+2215,U+FEFF,U+FFFD'
POLISH='U+0104-0107,U+0118-0119,U+0141-0144,U+015A-015B,U+0179-017C'

css='https://fonts.googleapis.com/css2?family=Instrument+Sans:wght@400;500;600&family=Newsreader:ital,wght@0,300;0,400;1,300;1,400'
source=$(mktemp -d)
trap 'rm -rf "$source"' EXIT

# Without a browser user agent the API answers with the full TrueType files.
curl -fsS -A curl "$css" | awk '
    /font-family/ { family = ($0 ~ /Instrument/) ? "InstrumentSans" : "Newsreader" }
    /font-style/  { italic = ($0 ~ /italic/) }
    /font-weight/ { gsub(/[^0-9]/, ""); weight = $0 }
    /src:/        { match($0, /https:[^)]+/); print family, weight, italic, substr($0, RSTART, RLENGTH) }
' | while read -r family weight italic url; do
    case "$family-$weight-$italic" in
        InstrumentSans-400-0) name=InstrumentSans-Regular ;;
        InstrumentSans-500-0) name=InstrumentSans-Medium ;;
        InstrumentSans-600-0) name=InstrumentSans-SemiBold ;;
        Newsreader-300-0) name=Newsreader-Light ;;
        Newsreader-300-1) name=Newsreader-LightItalic ;;
        Newsreader-400-0) name=Newsreader-Regular ;;
        Newsreader-400-1) name=Newsreader-Italic ;;
        *) continue ;;
    esac

    curl -fsS -o "$source/$name.ttf" "$url"
    pyftsubset "$source/$name.ttf" --unicodes="$LATIN,$POLISH" --layout-features='*' --flavor=woff2 --output-file="$name.woff2"
    echo "$name.woff2 $(wc -c < "$name.woff2") B"
done
