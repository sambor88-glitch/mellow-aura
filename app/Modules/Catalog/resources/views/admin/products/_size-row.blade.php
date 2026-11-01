{{--
    One size in the product card and in the new product form (MA-307): name, price, the price before a reduction
    (a column only while „Promocja” is on), pieces on the shelf with − and +, and ⋯ for what is rarely changed.
    $index is a number, or __i__ in the template the „+ Dodaj rozmiar” button copies.
--}}
@php
    $row ??= [];
    $isVoucher ??= false;
    $photos ??= collect();
    $first ??= false;
    $key = fn (string $field) => 'variants.'.$index.'.'.$field;
    $invalid = fn (string $field) => $bag->has($key($field));
    $name = filled($row['label'] ?? null) ? $row['label'] : 'rozmiar '.(is_int($index) ? $index + 1 : 'nowy');
    $saved = filled($row['id'] ?? null);
    $hidden = ! empty($row['is_hidden']);
    $hasMore = $saved || $isVoucher || $photos->count() > 1;
    $messages = collect(['label', 'price', 'compare_at', 'stock', 'media_id', 'voucher_seats'])->map(fn ($field) => $bag->first($key($field)))->filter();
    $moreOpen = $invalid('media_id') || $invalid('voucher_seats');
    $step = 'const i = $el.parentElement.querySelector(\'input\'); i.value = Math.max(0, (parseInt(i.value, 10) || 0) + %d); i.dispatchEvent(new Event(\'input\', { bubbles: true }))';
@endphp
<div class="size-grid size-row" x-data="{ more: @js($moreOpen) }" data-row>
    <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
    <label @class(['size-name grid min-w-0 gap-1', 'opacity-55' => $hidden])>
        <span class="text-[11.5px] text-label md:sr-only">Rozmiar</span>
        <input name="variants[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" placeholder="{{ $first ? 'np. Mały 12 cm' : 'kolejny rozmiar' }}"
               @if ($invalid('label')) aria-invalid="true" @endif class="field-control field-compact">
    </label>
    <label @class(['size-price grid min-w-0 gap-1', 'opacity-55' => $hidden])>
        <span class="text-[11.5px] text-label md:sr-only">Cena</span>
        <span class="field-affix"><input name="variants[{{ $index }}][price]" value="{{ $row['price'] ?? '' }}" inputmode="decimal" placeholder="0" aria-label="Cena: {{ $name }}"
                                         @if ($invalid('price')) aria-invalid="true" @endif><span>zł</span></span>
    </label>
    <label @class(['size-promo min-w-0 gap-1', 'opacity-55' => $hidden])>
        <span class="text-[11.5px] text-label md:sr-only">Przed obniżką</span>
        <span class="field-affix"><input name="variants[{{ $index }}][compare_at]" value="{{ $row['compare_at'] ?? '' }}" inputmode="decimal" placeholder="—" aria-label="Cena przed obniżką: {{ $name }}"
                                         x-bind:disabled="! promo" @disabled(! ($promo ?? false)) @if ($invalid('compare_at')) aria-invalid="true" @endif><span>zł</span></span>
    </label>
    <div @class(['size-stock grid min-w-0 gap-1', 'opacity-55' => $hidden])>
        <span aria-hidden="true" class="text-[11.5px] text-label md:hidden">Na półce</span>
        <span class="field-affix">
            <button type="button" tabindex="-1" aria-label="Mniej: {{ $name }}" x-on:click="{{ sprintf($step, -1) }}">−</button>
            <input name="variants[{{ $index }}][stock]" value="{{ $row['stock'] ?? '' }}" inputmode="numeric" placeholder="—" aria-label="Sztuk na półce: {{ $name }}"
                   @if ($invalid('stock')) aria-invalid="true" @endif>
            <button type="button" tabindex="-1" aria-label="Więcej: {{ $name }}" x-on:click="{{ sprintf($step, 1) }}">+</button>
        </span>
    </div>
    @if ($hasMore)
        <button type="button" x-on:click="more = ! more" aria-expanded="{{ $moreOpen ? 'true' : 'false' }}" x-bind:aria-expanded="more.toString()" aria-label="Więcej: {{ $name }}"
                class="size-more grid size-11 place-items-center rounded-full border border-transparent text-[18px] text-label hover:border-line-strong hover:bg-sand hover:text-ink aria-expanded:border-line-strong aria-expanded:bg-sand">⋯</button>
    @else
        <span class="size-more"></span>
    @endif

    @if ($hidden)
        <p class="size-wide text-[12px] text-label">Ukryty w sklepie — zostaje z historią cen i zapisami „Napisz, gdy wróci”. Odznacz w ⋯, a wróci.</p>
    @endif
    @if ($hasMore)
        <div x-show="more" @unless ($moreOpen) x-cloak @endunless class="size-wide flex flex-wrap items-center gap-x-5 gap-y-1 rounded-[4px] border border-divider bg-linen px-3.5 py-2">
            @if ($photos->count() > 1)
                <label class="flex min-h-11 items-center gap-2 text-[13px] text-label">Zdjęcie po wybraniu
                    <select name="variants[{{ $index }}][media_id]" aria-label="Zdjęcie, {{ $name }}" @if ($invalid('media_id')) aria-invalid="true" @endif class="field-control field-compact w-auto">
                        <option value="">pierwsze zdjęcie</option>
                        @foreach ($photos as $photo)
                            <option value="{{ $photo->id }}" @selected((int) ($row['media_id'] ?? 0) === $photo->id)>zdjęcie {{ $loop->iteration }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if ($isVoucher)
                <label class="flex min-h-11 items-center gap-2 text-[13px] text-label">
                    <input name="variants[{{ $index }}][voucher_seats]" value="{{ $row['voucher_seats'] ?? '' }}" inputmode="numeric" placeholder="1" aria-label="Miejsc na warsztacie, {{ $name }}"
                           @if ($invalid('voucher_seats')) aria-invalid="true" @endif class="field-control field-compact w-16 text-right"> miejsc
                </label>
                <label class="flex min-h-11 items-center gap-2 text-[13px] text-label">
                    <input type="checkbox" name="variants[{{ $index }}][sent_by_post]" value="1" @checked(! empty($row['sent_by_post'])) class="size-4 accent-ink"> pocztą
                </label>
            @endif
            @if ($saved)
                {{-- Hidden: off the shop, history and waiting customers kept (MA-279). Removed: gone for good. --}}
                <label class="flex min-h-11 items-center gap-2 text-[13px] text-label">
                    <input type="checkbox" name="variants[{{ $index }}][is_hidden]" value="1" @checked($hidden) class="size-4 accent-ink"> ukryj w sklepie
                </label>
                <x-shared::admin.remove-toggle :name="'variants['.$index.'][remove]'" />
            @endif
        </div>
    @endif
    @foreach ($messages as $message)
        <p class="size-wide text-[13px] text-error">{{ $message }}</p>
    @endforeach
</div>
