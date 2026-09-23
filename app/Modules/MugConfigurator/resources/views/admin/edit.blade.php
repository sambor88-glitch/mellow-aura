@use('App\Modules\MugConfigurator\Support\MugOptions')
@use('App\Modules\Shared\Support\Money')
@php
    $photo = $options->photoUrl();
    $position = $options->position();
    $ink = $options->ink();
    $inks = $options->inks();

    $photoErrors = $errors->getBag('zdjecie');
    $sizeErrors = $errors->getBag('rozmiary');
    $lookErrors = $errors->getBag('napis');
    $textErrors = $errors->getBag('teksty');

    $savedSizes = array_values((array) old('sizes', $options->sizes()->map(fn (array $size) => [
        'label' => $size['label'],
        'capacity' => $size['capacity_ml'],
        'price' => Money::input($size['price_gross']),
    ])->all()));
    // One empty row for a new size; left empty, it is not saved.
    $sizeRows = [...$savedSizes, ['label' => '', 'capacity' => '', 'price' => '']];
    $maxChars = (int) old('max_chars_per_line', $options->maxCharsPerLine());
    $maxLines = (int) old('max_lines', $options->maxLines());

    $look = [
        'x' => (int) old('x_percent', $position['x']),
        'y' => (int) old('y_percent', $position['y']),
        'size' => (int) old('size_percent', $position['size']),
        'rotation' => (int) old('rotation_deg', $position['rotation']),
        'ink' => (string) old('ink', $ink['code']),
    ];

    $textFields = [
        ['text_mug_heading', 'Nagłówek — Enter przenosi wyraz do nowej linii', 2],
        ['text_mug_lead', 'Wstęp pod nagłówkiem', 3],
        ['text_mug_note', 'Uwaga pod zdjęciem', 3],
        ['text_mug_lead_time', 'Czas realizacji — obok „na zamówienie”', 1],
        ['text_mug_bulk_order', '„Zamawiasz więcej?”', 2],
        ['text_mug_refusals', '„Czego nie wbiję”', 2],
    ];

    $card = 'scroll-mt-6 rounded-[4px] border border-line bg-cream px-[26px] py-7';
    $heading = 'mb-1 font-serif text-[25px]';
    $intro = 'mb-[18px] text-[13.5px] leading-[1.6] text-label';
    $alert = 'mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text';
    $input = 'min-w-0 rounded-[4px] border bg-white text-[14.5px] text-ink placeholder:text-hint focus:border-ink';
    $button = 'mt-[18px] min-h-11 w-full rounded-full bg-ink p-4 text-[14px] text-linen transition duration-300 hover:bg-navy active:scale-[.97]';
    $border = fn ($bag, string $key) => $bag->has($key) ? 'border-error' : 'border-line';
@endphp
<x-admin::layout title="Kubek z napisem" lead="Zdjęcie pod napisy, rozmiary z cenami i to, jak napis siedzi na kubku. Zmiany widać na stronie od razu.">
    <div class="flex flex-wrap items-start gap-[26px]">
        <div class="grid min-w-0 flex-[1_1_360px] gap-[22px]">
            <section id="zdjecie" class="{{ $card }}">
                <h2 class="{{ $heading }}">Zdjęcie kubka pod napisy</h2>
                <p class="{{ $intro }}">Na tym zdjęciu klientka widzi swój tekst. Najlepiej Twój własny kubek, sfotografowany prosto z boku, na jednolitym tle — i <strong class="font-medium">bez żadnego napisu</strong>, bo tekst ze strony nałoży się na istniejący.</p>
                @if ($photo)
                    <img src="{{ $photo }}" alt="Zdjęcie kubka w konfiguratorze" width="1200" height="1200" class="mb-3 block max-h-[240px] w-full rounded-[3px] bg-line-soft object-cover">
                @else
                    <div class="mb-3 grid h-[160px] place-items-center rounded-[3px] border border-dashed border-line-strong bg-sand-dark text-[13px] text-hint">Brak zdjęcia — strona pokaże pusty kwadrat</div>
                @endif
                <form method="post" action="{{ route('admin.mug.photo') }}" enctype="multipart/form-data" novalidate x-data="{ sending: false }">
                    @csrf
                    <label class="block cursor-pointer rounded-[4px] border border-dashed border-line-strong bg-linen p-5 text-center transition duration-300 hover:border-ink hover:bg-sand-dark active:scale-[.98] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                        <span class="block text-[14px] text-lead" x-text="sending ? 'Wgrywam zdjęcie…' : 'Wgraj inne zdjęcie kubka'">Wgraj inne zdjęcie kubka</span>
                        <span class="mt-1 block text-[12.5px] text-hint">JPG, PNG albo WebP z telefonu albo komputera, do 15 MB</span>
                        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only"
                               x-on:change="if ($el.files.length) { sending = true; $el.form.requestSubmit() }">
                    </label>
                    @if ($photoErrors->has('photo'))
                        <p role="alert" class="mt-1.5 text-[13px] text-error">{{ $photoErrors->first('photo') }}</p>
                    @endif
                    <noscript><button class="{{ $button }}">Wgraj wybrane zdjęcie</button></noscript>
                </form>
                @unless ($isDefaultPhoto)
                    <form method="post" action="{{ route('admin.mug.photo.reset') }}">
                        @csrf
                        @method('DELETE')
                        <button class="min-h-11 pt-2.5 text-[12.5px] text-label hover:text-ink">Przywróć domyślne zdjęcie</button>
                    </form>
                @endunless
            </section>

            <form id="rozmiary" method="post" action="{{ route('admin.mug.sizes') }}" novalidate class="{{ $card }}" x-data="{ chars: @js((string) $maxChars), lines: @js((string) $maxLines) }">
                @csrf
                @method('PUT')
                <h2 class="{{ $heading }}">Rozmiary, pojemność i ceny</h2>
                <p class="{{ $intro }}">Pojemność w mililitrach dopisuje się sama do nazwy. Zostaw puste, jeśli nie chcesz jej podawać.</p>
                @if ($sizeErrors->any())
                    <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
                @endif
                <div class="grid gap-2.5">
                    @foreach ($sizeRows as $index => $row)
                        <div class="flex flex-wrap items-center gap-[9px]">
                            <input name="sizes[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="{{ $index < count($savedSizes) ? 'nazwa' : 'nowy rozmiar' }}" aria-label="Rozmiar {{ $index + 1 }} — nazwa"
                                   class="{{ $input }} {{ $border($sizeErrors, 'sizes.'.$index.'.label') }} min-h-11 flex-[1_1_90px] px-3">
                            <span class="flex items-center gap-[5px]">
                                <input name="sizes[{{ $index }}][capacity]" value="{{ $row['capacity'] ?? '' }}" inputmode="numeric" placeholder="—" aria-label="Rozmiar {{ $index + 1 }} — pojemność w ml"
                                       class="{{ $input }} {{ $border($sizeErrors, 'sizes.'.$index.'.capacity') }} min-h-11 w-[62px] px-2.5 text-right">
                                <span class="text-[12.5px] text-label">ml</span>
                            </span>
                            <span class="flex items-center gap-[5px]">
                                <input name="sizes[{{ $index }}][price]" value="{{ $row['price'] ?? '' }}" inputmode="decimal" placeholder="cena" aria-label="Rozmiar {{ $index + 1 }} — cena w zł"
                                       class="{{ $input }} {{ $border($sizeErrors, 'sizes.'.$index.'.price') }} min-h-11 w-[72px] px-2.5 text-right">
                                <span class="text-[12.5px] text-label">zł</span>
                            </span>
                            @if ($index < count($savedSizes))
                                <label class="flex min-h-11 items-center gap-1.5 text-[12.5px] text-label">
                                    <input type="checkbox" name="sizes[{{ $index }}][remove]" value="1" class="size-4 accent-error"> usuń
                                </label>
                            @endif
                        </div>
                        @foreach (['label', 'capacity', 'price'] as $field)
                            @if ($sizeErrors->has('sizes.'.$index.'.'.$field))
                                <p class="text-[13px] text-error">{{ $sizeErrors->first('sizes.'.$index.'.'.$field) }}</p>
                            @endif
                        @endforeach
                    @endforeach
                    @if ($sizeErrors->has('sizes'))
                        <p class="text-[13px] text-error">{{ $sizeErrors->first('sizes') }}</p>
                    @endif
                </div>
                <p class="mt-2.5 text-[12.5px] text-hint">W pusty wiersz wpisz nowy rozmiar. Kubek z napisem powstaje na zamówienie, więc nie ma stanu na półce.</p>

                <div class="mt-[26px] border-t border-sand-dark pt-[22px]">
                    <h3 class="mb-1 font-serif text-[21px]">Ile znaków wolno wpisać</h3>
                    <p class="mb-4 text-[13.5px] text-label">Tyle, ile realnie wbijesz stemplem na ściance. Powyżej limitu pole po prostu przestaje przyjmować litery.</p>
                    <div class="flex flex-wrap gap-3.5">
                        <label class="min-w-0 flex-[1_1_130px]">
                            <span class="mb-1.5 block text-[12.5px] text-muted">Znaków w linii</span>
                            <input name="max_chars_per_line" value="{{ $maxChars }}" x-model="chars" inputmode="numeric"
                                   class="{{ $input }} {{ $border($sizeErrors, 'max_chars_per_line') }} min-h-11 w-full px-3.5 text-center">
                        </label>
                        <label class="min-w-0 flex-[1_1_130px]">
                            <span class="mb-1.5 block text-[12.5px] text-muted">Liczba linii</span>
                            <input name="max_lines" value="{{ $maxLines }}" x-model="lines" inputmode="numeric"
                                   class="{{ $input }} {{ $border($sizeErrors, 'max_lines') }} min-h-11 w-full px-3.5 text-center">
                        </label>
                    </div>
                    @foreach (['max_chars_per_line', 'max_lines'] as $field)
                        @if ($sizeErrors->has($field))
                            <p class="mt-1.5 text-[13px] text-error">{{ $sizeErrors->first($field) }}</p>
                        @endif
                    @endforeach
                    <p class="mt-3 text-[12.5px] leading-[1.5] text-hint" aria-live="polite">
                        Klientka wpisze najwyżej <span x-text="lines">{{ $maxLines }}</span> × <span x-text="chars">{{ $maxChars }}</span> znaków,
                        czyli <span x-text="(parseInt(lines, 10) || 0) * (parseInt(chars, 10) || 0)">{{ $maxLines * $maxChars }}</span> liter do wbicia.
                        Najwyżej {{ MugOptions::MAX_LINES }} linii po {{ MugOptions::MAX_CHARS_PER_LINE }} znaków.
                    </p>
                </div>
                <button class="{{ $button }}">Zapisz rozmiary i limit znaków</button>
            </form>
        </div>

        <div class="grid min-w-0 flex-[1_1_320px] gap-[22px]">
            <form id="napis" method="post" action="{{ route('admin.mug.look') }}" novalidate class="{{ $card }}"
                  x-data="{ x: @js($look['x']), y: @js($look['y']), size: @js($look['size']), rotation: @js($look['rotation']), ink: @js($look['ink']), inks: @js($inks->keyBy('code')) }">
                @csrf
                @method('PUT')
                <h2 class="{{ $heading }}">Gdzie ma siedzieć napis</h2>
                <p class="{{ $intro }}">Ustaw raz pod swoje zdjęcie. Podgląd pokazuje efekt na żywo.</p>
                @if ($lookErrors->any())
                    <p role="alert" class="{{ $alert }}">{{ $lookErrors->first() }}</p>
                @endif
                <div class="relative mb-5 overflow-hidden rounded-[4px] bg-line-soft">
                    @if ($photo)
                        <img src="{{ $photo }}" alt="Podgląd ustawienia napisu" width="1200" height="1200" class="block aspect-square w-full object-cover">
                    @else
                        <div class="aspect-square w-full"></div>
                    @endif
                    <div aria-hidden="true" class="absolute w-[60%] text-center text-[15px]"
                         style="left: {{ $look['x'] }}%; top: {{ $look['y'] }}%; transform: translate(-50%, -50%) rotate({{ $look['rotation'] }}deg)"
                         x-bind:style="{ left: x + '%', top: y + '%', transform: 'translate(-50%, -50%) rotate(' + rotation + 'deg)' }">
                        <span class="block leading-[1.35] tracking-[0.16em] [overflow-wrap:anywhere]"
                              style="font-size: {{ $look['size'] }}%; color: {{ $ink['hex'] }}"
                              x-bind:style="{ fontSize: size + '%', color: inks[ink]?.hex }">TWÓJ NAPIS</span>
                    </div>
                </div>

                <fieldset>
                    <legend class="mb-1 text-[12.5px] text-muted">Kolor napisu — <span x-text="inks[ink]?.name">{{ $ink['name'] }}</span></legend>
                    <p class="mb-2.5 text-[12px] leading-[1.5] text-hint">Tylko dla Ciebie — klientki tego nie wybierają. Dopasuj do kubka, który masz w tle.</p>
                    <div class="mb-5 flex flex-wrap gap-2.5">
                        @foreach ($inks as $colour)
                            <label title="{{ $colour['name'] }}" style="background-color: {{ $colour['hex'] }}"
                                   class="relative size-[34px] cursor-pointer rounded-full border-2 border-transparent shadow-[inset_0_0_0_2px_var(--color-cream),inset_0_0_0_3px_rgba(47,38,32,.22)] after:absolute after:-inset-1.5 has-checked:border-ink has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                <input type="radio" name="ink" value="{{ $colour['code'] }}" x-model="ink" @checked($look['ink'] === $colour['code']) class="sr-only">
                                <span class="sr-only">{{ $colour['name'] }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <div class="grid gap-3.5">
                    @foreach ([['x_percent', 'x', 'W poziomie', 10, 90, '%'], ['y_percent', 'y', 'W pionie', 10, 90, '%'], ['size_percent', 'size', 'Wielkość liter', 50, 220, '%'], ['rotation_deg', 'rotation', 'Przekrzywienie', -15, 15, '°']] as [$name, $model, $label, $min, $max, $unit])
                        <label class="block">
                            <span class="mb-1.5 block text-[12.5px] text-muted">{{ $label }} — <span x-text="{{ $model }} + '{{ $unit }}'">{{ $look[$model] }}{{ $unit }}</span></span>
                            <input type="range" name="{{ $name }}" min="{{ $min }}" max="{{ $max }}" value="{{ $look[$model] }}" x-model.number="{{ $model }}" class="h-11 w-full accent-ink">
                        </label>
                    @endforeach
                </div>
                <button class="{{ $button }}">Zapisz położenie i kolor napisu</button>
            </form>

            <form id="teksty" method="post" action="{{ route('admin.mug.texts') }}" novalidate class="{{ $card }}">
                @csrf
                @method('PUT')
                <h2 class="{{ $heading }}">Teksty na stronie kubka</h2>
                <p class="{{ $intro }}">Puste zdanie nie pokaże się na stronie.</p>
                @if ($textErrors->any())
                    <p role="alert" class="{{ $alert }}">Popraw zaznaczone pola, żeby zapisać.</p>
                @endif
                <div class="grid gap-3.5">
                    @foreach ($textFields as [$key, $label, $rows])
                        <div class="min-w-0">
                            <label for="teksty-{{ $key }}" class="mb-1.5 block text-[11.5px] tracking-[0.1em] text-label uppercase">{{ $label }}</label>
                            <textarea id="teksty-{{ $key }}" name="{{ $key }}" rows="{{ $rows }}"
                                      class="{{ $input }} {{ $border($textErrors, $key) }} w-full resize-y px-3.5 py-3 leading-[1.55]">{{ old($key, $texts[$key]) }}</textarea>
                            @if ($textErrors->has($key))
                                <p class="mt-1.5 text-[13px] text-error">{{ $textErrors->first($key) }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
                <button class="{{ $button }}">Zapisz teksty na stronie</button>
            </form>
        </div>
    </div>
</x-admin::layout>
