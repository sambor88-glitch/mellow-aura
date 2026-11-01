@use('App\Modules\Catalog\Actions\ConvertEuroPrices')
@use('App\Modules\Catalog\Enums\Dimension')
@use('App\Modules\Catalog\Enums\FoodContact')
@use('App\Modules\Catalog\Enums\GoogleCategory')
@use('App\Modules\Catalog\Enums\Occasion')
@use('App\Modules\Catalog\Enums\Recipient')
@use('App\Modules\Catalog\Enums\VariantKind')
@use('App\Modules\Catalog\Models\ExchangeRate')
@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\Shared\Support\FocalPoint')
@use('App\Modules\Shared\Support\Money')
@use('App\Modules\Shared\Support\Seo')
@use('App\Modules\Workshops\Support\WorkshopTypes')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    // The card of one product (MA-307): one form in tabs, so saving sends every tab at once.
    $formKey = 'produkt-'.$product->id;
    $bag = $errors->getBag($formKey);
    $photoBag = $errors->getBag('zdjecia-'.$product->id);
    $mine = old('form') === $formKey;
    $old = fn (string $key, mixed $default = null) => $mine ? old($key, $default) : $default;

    $rows = $mine
        ? array_values((array) old('variants', []))
        : $product->variants->map(fn ($variant) => [
            'id' => $variant->id,
            'label' => $variant->label,
            'label_en' => $variant->translation('en')?->label,
            'price' => Money::input($variant->price_gross),
            'compare_at' => $variant->compare_at_price === null ? '' : Money::input($variant->compare_at_price),
            'price_eur' => ($eur = $variant->price('EUR')) === null ? '' : Money::input($eur),
            'compare_at_eur' => ($eurBefore = $variant->compareAtPrice('EUR')) === null ? '' : Money::input($eurBefore),
            'stock' => $variant->stock,
            'sent_by_post' => $variant->sent_by_post,
            'voucher_seats' => $variant->voucher_seats,
            'media_id' => $variant->media_id,
            'is_hidden' => $variant->is_hidden,
        ])->all();
    $rows = $rows ?: [['id' => null, 'label' => '', 'price' => '', 'stock' => '']];
    // „Przed obniżką” is a column only during a promotion; switching it off sends no price before a reduction.
    $promo = collect($rows)->contains(fn ($row) => filled($row['compare_at'] ?? null) || filled($row['compare_at_eur'] ?? null));
    // A voucher goes out as a PDF by e-mail; a size ticked „pocztą” is printed and posted, so its buyer chooses a delivery.
    $isVoucher = $product->isVoucher();
    // A workshop voucher books its workshop on the site with the code (MA-137); without one it books nothing.
    $workshops = $isVoucher ? app(WorkshopTypes::class)->all() : collect();
    $workshopCode = (string) $old('workshop_code', $product->workshop_code);
    $photos = $product->getMedia('images');

    $dimensions = (array) $old('dimensions', $product->dimensions ?? []);
    $occasions = (array) $old('occasions', $product->occasions ?? []);
    $recipients = (array) $old('recipients', $product->recipients ?? []);
    $published = $mine ? (bool) old('is_published') : $product->is_published;
    $oneOff = $mine ? (bool) old('is_one_off') : (bool) $product->is_one_off;
    $exactPiece = $mine ? (bool) old('is_exact_piece') : (bool) $product->is_exact_piece;
    $fromRate = $mine ? (bool) old('euro_from_rate') : (bool) $product->euro_from_rate;
    $euroRate = ExchangeRate::for('EUR');
    $euroMarkup = (int) $settings->get(ConvertEuroPrices::MARKUP, 0);
    $foodContact = (string) $old('food_contact', $product->food_contact?->value);
    $variantKind = (string) $old('variant_kind', ($product->variant_kind ?? VariantKind::Options)->value);
    $googleCategory = (string) $old('google_category', $product->google_category?->value);
    $showInGoogle = $mine ? (bool) old('show_in_google') : $product->show_in_google;
    $defaultTolerance = $settings->get('size_tolerance');
    // The English version, written here even while the English shop is still switched off.
    $english = $product->translation('en');
    $en = fn (string $field) => $old('en.'.$field, $english?->{$field});

    // Every mistake belongs to a tab: the card opens the first one with a mistake and marks the others.
    $tabOf = fn (string $key) => match (true) {
        str_starts_with($key, 'en.') || (bool) preg_match('/^variants\.\d+\.(label_en|price_eur|compare_at_eur)$/', $key) => 'en',
        str_starts_with($key, 'variants') || in_array($key, ['variant_kind', 'workshop_code'], true) => 'sale',
        str_starts_with($key, 'dimensions') || in_array($key, ['size_tolerance', 'food_contact', 'care_note', 'deviation', 'safety_warnings'], true) => 'details',
        str_starts_with($key, 'occasions') || str_starts_with($key, 'recipients') || in_array($key, ['seo_description', 'google_category'], true) => 'gift',
        default => 'desc',
    };
    $errorTabs = collect($bag->keys())->map($tabOf)->unique()->values();
    $tab = $errorTabs->first() ?? ($photoBag->any() || $errors->getBag('model-'.$product->id)->any() ? 'desc' : $tab);
    $tabs = ['sale' => 'Ceny i sztuki', 'desc' => 'Opis i zdjęcia', 'details' => 'Szczegóły', 'gift' => 'Prezent i Google', 'en' => 'English'];
    $englishOn = Locales::isEnabled('en');

    // What the header and the shop card show.
    $visible = $product->variants->reject(fn ($variant) => $variant->is_hidden);
    $prices = $visible->pluck('price_gross');
    $price = $prices->isEmpty() ? '—' : ($prices->min() === $prices->max() ? Money::format($prices->min()) : 'od '.Money::format($prices->min()));
    $counted = $visible->contains(fn ($variant) => $variant->stock !== null);
    $stock = $counted ? $visible->sum(fn ($variant) => (int) $variant->stock).' szt.' : 'bez liczenia sztuk';
    $cover = $photos->first();
    $ready = [
        ['Zdjęcia', $photos->isNotEmpty(), $photos->isNotEmpty() ? trans_choice(':count zdjęcie|:count zdjęcia|:count zdjęć', $photos->count(), [], 'pl') : 'bez zdjęcia nie trafi do Google', 'desc', null, true],
        ['Opis', filled($product->description), filled($product->description) ? mb_strlen($product->description).' znaków' : 'bez opisu karta jest pusta', 'desc', $formKey.'-description', true],
        ['Ceny', $prices->isNotEmpty(), $price, 'sale', null, true],
        ['Wymiary', filled($product->dimensions), filled($product->dimensions) ? collect(Dimension::cases())->filter(fn ($dimension) => filled($product->dimensions[$dimension->value] ?? null))->map(fn ($dimension) => mb_strtolower($dimension->label()))->join(', ') : 'nieobowiązkowe', 'details', null, false],
        ['Skąd ten pomysł', filled($product->story), filled($product->story) ? 'jest' : 'pokaże się pod produktem', 'desc', $formKey.'-story', false],
        ['Pielęgnacja', filled($product->care_note), filled($product->care_note) ? 'jest' : 'jedno zdanie na karcie', 'details', $formKey.'-care', false],
        ['Wersja angielska', filled($english), filled($english) ? 'gotowa' : 'na później, po starcie sprzedaży do UE', 'en', $formKey.'-en-name', false],
    ];
    $done = collect($ready)->filter(fn ($item) => $item[1])->count();

    $legend = 'font-serif text-[24px] leading-[1.15] font-light';
    $sub = 'mt-2 border-t border-divider pt-[18px] font-serif text-[21px] font-light';
    $lead = 'text-[13px] text-muted';
    $note = 'mt-1.5 text-[12.5px] leading-[1.5] text-hint';
    $chip = 'flex cursor-pointer items-center rounded-full border border-line-strong bg-cream px-4 py-2 text-[13px] text-lead hover:border-ink has-checked:border-ink has-checked:bg-ink has-checked:text-linen has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy';
    $switchRow = 'flex min-h-11 cursor-pointer items-start gap-3 text-[14px] text-graphite';
    $error = fn (string $key) => $bag->has($key) ? '<p class="mt-1.5 text-[13px] text-error">'.e($bag->first($key)).'</p>' : '';
    $tabDot = fn (string $key) => $errorTabs->contains($key) || ($key === 'desc' && (blank($product->description) || $photos->isEmpty()));
    $editUrl = fn (int $id) => route('admin.products.edit', $id);
    // The tabs of the card in the shared component (MA-315): the number of sizes, „na później” and a dot where something is missing.
    $tabItems = collect($tabs)->map(fn (string $label, string $key) => [
        'label' => $label,
        'count' => $key === 'sale' ? count($rows) : null,
        'note' => $key === 'en' && ! $englishOn ? 'na później' : null,
        'dot' => $tabDot($key) ? ($errorTabs->contains($key) ? 'error' : 'todo') : null,
    ])->all();
@endphp
<x-admin::layout :title="$product->name" :heading="false">
    <div x-data="productCard(@js($promo))">
      <x-admin::tabs :tabs="$tabItems" label="Części karty produktu" :active="$tab">
       <x-slot:head>
        <div class="-mt-2 mb-2 flex items-center justify-between gap-3">
            <a href="{{ route('admin.products.index') }}" class="inline-flex min-h-11 items-center gap-1.5 text-[13px] text-lead hover:text-ink">← Produkty</a>
            <div role="group" aria-label="Inny produkt" class="flex items-center gap-0.5 text-[12.5px] text-label tabular-nums">
                @if ($previous)
                    <a href="{{ $editUrl($previous) }}" x-bind:href="@js($editUrl($previous)) + '?tab=' + tab" aria-label="Poprzedni produkt" class="grid size-11 place-items-center rounded-full border border-transparent text-[18px] text-lead hover:border-line-strong hover:bg-sand hover:text-ink">‹</a>
                @else
                    <span aria-hidden="true" class="grid size-11 place-items-center text-[18px] opacity-35">‹</span>
                @endif
                <span class="min-w-[52px] text-center">{{ $position }} z {{ $total }}</span>
                @if ($next)
                    <a href="{{ $editUrl($next) }}" x-bind:href="@js($editUrl($next)) + '?tab=' + tab" aria-label="Następny produkt" class="grid size-11 place-items-center rounded-full border border-transparent text-[18px] text-lead hover:border-line-strong hover:bg-sand hover:text-ink">›</a>
                @else
                    <span aria-hidden="true" class="grid size-11 place-items-center text-[18px] opacity-35">›</span>
                @endif
            </div>
        </div>

        <div class="mb-1 flex flex-wrap items-center gap-x-[18px] gap-y-3">
            @if ($cover)
                <img src="{{ $cover->getAvailableUrl(['thumb']) }}" alt="" width="56" height="70" style="{{ FocalPoint::style($cover) }}" class="h-[60px] w-12 flex-none rounded-[8px] object-cover md:h-[70px] md:w-14">
            @else
                <span class="grid h-[60px] w-12 flex-none place-items-center rounded-[8px] border border-dashed border-line-strong bg-sand-dark text-[10.5px] text-label md:h-[70px] md:w-14">brak</span>
            @endif
            <div class="min-w-0 flex-[1_1_0] md:flex-[1_1_260px]">
                <h1 class="font-serif text-[length:clamp(28px,4vw,44px)] leading-[1.05] font-light text-balance">{{ $product->name }}</h1>
                <p class="mt-1.5 text-[13px] text-label">{{ $product->category->name }} &middot; <span class="text-lead tabular-nums">{{ $price }}</span> &middot; <span class="text-lead tabular-nums">{{ $stock }}</span></p>
            </div>
            <div class="flex flex-[1_1_100%] items-center gap-2 md:flex-none">
                <label x-data="{ on: @js($published) }" class="inline-flex min-h-11 flex-1 cursor-pointer items-center gap-2.5 rounded-full border border-line-strong bg-cream py-0 pr-4 pl-2 text-[13px] whitespace-nowrap text-lead hover:border-ink md:flex-none">
                    <input type="checkbox" form="{{ $formKey }}" name="is_published" value="1" x-model="on" @checked($published) class="peer sr-only">
                    <span aria-hidden="true" class="switch-track"></span>
                    <span><span x-text="on ? 'Widoczny' : 'Ukryty'">{{ $published ? 'Widoczny' : 'Ukryty' }}</span> w sklepie</span>
                </label>
                @if ($product->is_published)
                    <x-shared::button href="{{ route('product.show', $product) }}" target="_blank" variant="glass" size="md">Zobacz w sklepie ↗</x-shared::button>
                @endif
                {{-- A hidden copy right after this product, with its name ready to change (MA-307). --}}
                <form method="post" action="{{ route('admin.products.duplicate', $product) }}" class="flex-none">
                    @csrf
                    <x-shared::button type="submit" variant="glass" size="md">Duplikuj</x-shared::button>
                </form>
            </div>
        </div>

       </x-slot:head>

        <div class="flex flex-wrap items-start gap-[22px]">
            <form id="{{ $formKey }}" method="post" action="{{ route('admin.products.update', $product) }}" novalidate data-unsaved-name="{{ $product->name }}"
                  class="min-w-0 flex-[1_1_520px] rounded-[4px] border border-line bg-cream px-3.5 pt-[18px] pb-3 md:px-[26px] md:pt-[26px] md:pb-[18px]">
                @csrf
                @method('PUT')
                <input type="hidden" name="form" value="{{ $formKey }}">
                <input type="hidden" name="tab" value="{{ $tab }}" x-bind:value="tab">

                @if ($bag->any())
                    <p role="alert" class="mb-4 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">
                        Popraw zaznaczone pola, żeby zapisać zmiany.@if ($errorTabs->count() > 1) Błędy są też w zakładkach z czerwoną kropką.@endif
                    </p>
                @endif

                {{-- Ceny i sztuki — the most common change, so the card opens here. --}}
                <x-admin::tab-panel name="sale" class="grid gap-[18px]">
                    <div>
                        <h2 class="{{ $legend }}">Ceny i sztuki</h2>
                        <p class="{{ $lead }} mt-1">Enter zapisuje. Puste „Na półce” — nie liczysz sztuk; przy zerze rozmiar sam znika ze sklepu.</p>
                    </div>
                    <div x-data="{ next: {{ count($rows) }} }" x-bind:class="{ 'is-promo': promo }" class="grid gap-[18px]">
                        <div class="grid gap-2">
                            <div aria-hidden="true" class="size-grid size-head px-0.5 text-[10.5px] tracking-[0.12em] text-label uppercase">
                                <span>Rozmiar</span><span>Cena</span><span class="size-promo">Przed obniżką</span><span>Na półce</span><span></span>
                            </div>
                            <div x-ref="rows" class="grid gap-2">
                                @foreach ($rows as $index => $row)
                                    @include('catalog::admin.products._size-row', ['index' => $index, 'row' => $row, 'bag' => $bag, 'isVoucher' => $isVoucher, 'photos' => $photos, 'promo' => $promo, 'first' => $index === 0])
                                @endforeach
                            </div>
                            <template x-ref="row">@include('catalog::admin.products._size-row', ['index' => '__i__', 'row' => [], 'bag' => $bag, 'isVoucher' => $isVoucher, 'photos' => $photos, 'promo' => $promo])</template>
                            {!! $error('variants') !!}
                        </div>
                        <button type="button" x-on:click="$refs.rows.insertAdjacentHTML('beforeend', $refs.row.innerHTML.replaceAll('__i__', next++)); $nextTick(() => $refs.rows.lastElementChild.querySelector('input:not([type=hidden])').focus())"
                                class="flex min-h-[46px] w-full items-center justify-center rounded-[14px] border border-dashed border-line-strong bg-linen text-[13.5px] text-lead hover:border-ink hover:text-ink">+ Dodaj rozmiar</button>
                        <label class="{{ $switchRow }}">
                            <input type="checkbox" name="promo" value="1" x-model="promo" @checked($promo) class="peer sr-only">
                            <span aria-hidden="true" class="switch-track mt-px"></span>
                            <span>Promocja<span class="mt-0.5 block text-[12.5px] text-hint">Pokaże pole „Przed obniżką”. Karta produktu przekreśli starą cenę i sama poda najniższą cenę z 30 dni.</span></span>
                        </label>
                    </div>
                    <div class="min-w-0">
                        <label for="{{ $formKey }}-variant-kind" class="mb-1.5 block text-[13.5px] text-graphite">Czym różnią się rozmiary</label>
                        <select id="{{ $formKey }}-variant-kind" name="variant_kind" aria-describedby="{{ $formKey }}-variant-kind-note" @if ($bag->has('variant_kind')) aria-invalid="true" @endif class="field-control">
                            @foreach (VariantKind::cases() as $option)
                                <option value="{{ $option->value }}" @selected($variantKind === $option->value)>{{ $option->option() }}</option>
                            @endforeach
                        </select>
                        @if ($bag->has('variant_kind'))
                            <p id="{{ $formKey }}-variant-kind-note" class="mt-1.5 text-[13px] text-error">{{ $bag->first('variant_kind') }}</p>
                        @else
                            <p id="{{ $formKey }}-variant-kind-note" class="{{ $note }}">Tak policzę je pod zdjęciem w sklepie. Przy jednej cenie nic się nie pokaże.</p>
                        @endif
                    </div>
                    @if ($isVoucher)
                        <div class="min-w-0">
                            <label for="{{ $formKey }}-workshop" class="mb-1.5 block text-[13.5px] text-graphite">Na warsztat</label>
                            <select id="{{ $formKey }}-workshop" name="workshop_code" aria-describedby="{{ $formKey }}-workshop-note" @if ($bag->has('workshop_code')) aria-invalid="true" @endif class="field-control">
                                <option value="">Żaden — voucher kwotowy</option>
                                @foreach ($workshops as $workshop)
                                    <option value="{{ WorkshopTypes::key($workshop) }}" @selected($workshopCode === WorkshopTypes::key($workshop))>{{ $workshop['name'] }}</option>
                                @endforeach
                            </select>
                            {!! $error('workshop_code') !!}
                            <p id="{{ $formKey }}-workshop-note" class="{{ $note }}">Z kodem takiego vouchera można się zapisać na stronie na termin tego warsztatu, bez płacenia. Voucher kwotowy rozliczasz ręcznie.</p>
                        </div>
                        <p class="{{ $note }} mt-0">Voucher idzie mailem w PDF, bez kosztów dostawy. Zaznacz „pocztą” przy rozmiarze, który drukujesz i wysyłasz — wtedy zamówienie zapyta o dostawę. „Miejsc” to liczba miejsc, które voucher rezerwuje na warsztacie — puste pole to jedno. Oba są pod ⋯ przy rozmiarze.</p>
                    @endif
                    @if ($photos->count() > 1)
                        <p class="{{ $note }} mt-0">„Zdjęcie po wybraniu” to fotka, którą karta produktu pokaże po wybraniu tego rozmiaru — np. kubek z tym napisem. Ustawisz je pod ⋯ przy rozmiarze.</p>
                    @endif
                    <p class="flex flex-wrap items-center gap-x-1.5 rounded-[4px] bg-sand px-3.5 py-3 text-[13px] text-lead">Ceny w euro i nazwy rozmiarów po angielsku są w zakładce
                        <button type="button" x-on:click="show('en')" class="min-h-8 text-brown underline decoration-line-strong underline-offset-[3px] hover:text-ink">English</button></p>
                </x-admin::tab-panel>

                {{-- Opis i zdjęcia --}}
                <x-admin::tab-panel name="desc" class="grid gap-[18px]">
                    <div id="zdjecia" class="scroll-mt-24">
                        <h2 class="{{ $legend }}">Zdjęcia</h2>
                        <p class="{{ $lead }} mt-1">Pierwsze to okładka w sklepie. Strzałkami ‹ › zmieniasz kolejność.</p>
                    </div>
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(96px,1fr))] gap-2.5">
                        @foreach ($photos as $photo)
                            <div @class(['relative aspect-[4/5] overflow-hidden rounded-[12px] border bg-line-soft', 'border-ink' => $loop->first, 'border-line' => ! $loop->first])>
                                <img src="{{ $photo->getAvailableUrl(['thumb']) }}" alt="{{ $photo->getCustomProperty('alt') ?: 'Zdjęcie '.$loop->iteration }}" loading="lazy" style="{{ FocalPoint::style($photo) }}" class="size-full object-cover">
                                @if ($loop->first)
                                    <span class="absolute top-1.5 left-1.5 rounded-full bg-glass-dark px-2 py-0.5 text-[10.5px] text-linen backdrop-blur-md">okładka</span>
                                @endif
                                <span class="absolute inset-x-1.5 bottom-1.5 flex items-center gap-1">
                                    @unless ($loop->first)
                                        <button form="zdjecie-{{ $photo->id }}-lewo" aria-label="Zdjęcie {{ $loop->iteration }} w lewo" class="relative grid size-[26px] place-items-center rounded-full bg-cream/85 text-[14px] leading-none text-ink after:absolute after:-inset-2.5 hover:bg-cream">‹</button>
                                    @endunless
                                    @unless ($loop->last)
                                        <button form="zdjecie-{{ $photo->id }}-prawo" aria-label="Zdjęcie {{ $loop->iteration }} w prawo" class="relative grid size-[26px] place-items-center rounded-full bg-cream/85 text-[14px] leading-none text-ink after:absolute after:-inset-2.5 hover:bg-cream">›</button>
                                    @endunless
                                    <button form="zdjecie-{{ $photo->id }}-usun" aria-label="Usuń zdjęcie {{ $loop->iteration }}" class="relative ml-auto grid size-[26px] place-items-center rounded-full bg-ink/75 text-[13px] leading-none text-linen after:absolute after:-inset-2.5 hover:bg-error">×</button>
                                </span>
                            </div>
                        @endforeach
                        <label x-data="photoPicker(@js($photoLimits))" x-on:pageshow.window="sending = false" x-bind:class="sending && 'pointer-events-none opacity-60'"
                               class="relative grid aspect-[4/5] cursor-pointer place-items-center content-center gap-1 rounded-[12px] border border-dashed border-line-strong bg-linen p-1.5 text-center text-[12px] text-lead transition-colors hover:border-ink hover:bg-sand-dark has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                            <span aria-hidden="true" class="font-serif text-[26px] leading-none font-light text-brown" x-text="sending ? '…' : '+'">+</span>
                            <span x-text="sending ? 'Dodaję…' : 'Dodaj zdjęcia'">Dodaj zdjęcia</span>
                            <input type="file" form="zdjecia-dodaj" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple x-on:change="send($el)" aria-label="Dodaj zdjęcia: {{ $product->name }}" class="sr-only">
                            <span role="alert" x-text="error" class="absolute inset-x-1 top-full mt-1 text-[12px] text-error empty:hidden"></span>
                        </label>
                    </div>
                    @foreach (collect($photoBag->all())->unique() as $message)
                        <p class="text-[13px] text-error">{{ $message }}</p>
                    @endforeach
                    @if ($photos->isNotEmpty())
                        @php($photoErrors = collect($bag->keys())->contains(fn ($key) => str_starts_with($key, 'photo_')))
                        <details @if ($photoErrors) open @endif class="group rounded-[4px] border border-divider bg-linen px-4 py-1">
                            <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between text-[14px] text-graphite [&::-webkit-details-marker]:hidden">
                                Opisy i kadr zdjęć <span aria-hidden="true" class="text-[18px] text-label transition-transform group-open:rotate-45">+</span>
                            </summary>
                            <p class="{{ $note }} mb-3">Opis jest dla Google i czytników ekranu — napisz, co widać. Sklep tnie zdjęcia do pionu, strona produktu do kwadratu: jeśli coś ważnego się ucina, przesuń kadr.</p>
                            <div class="mb-3 grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-3.5">
                                @foreach ($photos as $photo)
                                    @php($point = $old('photo_focus.'.$photo->id, FocalPoint::of($photo)))
                                    <div class="min-w-0">
                                        <x-shared::focus-picker name="photo_focus[{{ $photo->id }}]" :src="$photo->getAvailableUrl(['thumb'])" :x="$point['x'] ?? 50" :y="$point['y'] ?? 50"
                                                                label="Kadr zdjęcia {{ $loop->iteration }}" :frames="['aspect-[4/5]' => 'w sklepie', 'aspect-square' => 'na stronie produktu']" />
                                        <input name="photo_alts[{{ $photo->id }}]" value="{{ $old('photo_alts.'.$photo->id, $photo->getCustomProperty('alt')) }}" maxlength="160"
                                               aria-label="Opis zdjęcia {{ $loop->iteration }}" placeholder="{{ $product->name }}" @if ($bag->has('photo_alts.'.$photo->id)) aria-invalid="true" @endif class="field-control field-compact mt-1.5">
                                        {!! $error('photo_alts.'.$photo->id) !!}
                                        {!! $error('photo_focus.'.$photo->id.'.x') ?: $error('photo_focus.'.$photo->id.'.y') !!}
                                    </div>
                                @endforeach
                            </div>
                        </details>
                    @endif
                    {{-- „Zobacz u siebie” on the product page (MA-310): only with a model here. The forms sit outside the product form. --}}
                    @php($arModel = $product->arModel())
                    @php($modelErrors = collect($errors->getBag('model-'.$product->id)->all())->unique())
                    <div id="model-3d" class="grid scroll-mt-24 gap-2 rounded-[4px] border border-divider bg-linen px-4 py-3">
                        <p class="text-[14px] text-graphite">{{ $arModel ? 'Model 3D — klienci zobaczą go u siebie' : 'Model 3D — jeszcze żadnego' }}</p>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($arModel)
                                <span class="min-w-0 text-[13px] text-lead break-all">{{ $arModel->file_name }}</span>
                                <span class="text-[12px] text-hint tabular-nums">{{ $arModel->human_readable_size }}</span>
                            @endif
                            <label x-data="{ sending: false }" x-on:pageshow.window="sending = false" x-bind:class="sending && 'pointer-events-none opacity-60'"
                                   class="inline-flex min-h-11 cursor-pointer items-center rounded-full border border-line-strong bg-cream px-4 text-[12.5px] text-lead hover:border-ink has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                <span x-text="sending ? 'Wysyłam model…' : @js($arModel ? 'Podmień model' : 'Dodaj model (GLB)')">{{ $arModel ? 'Podmień model' : 'Dodaj model (GLB)' }}</span>
                                <span class="sr-only">: {{ $product->name }}</span>
                                <input type="file" form="model-dodaj" name="model" accept=".glb,model/gltf-binary" x-on:change="if ($el.files.length) { sending = true; $el.form.requestSubmit() }" class="sr-only">
                            </label>
                            @if ($arModel)
                                <button form="model-usun" class="min-h-11 rounded-full px-3 text-[12.5px] text-error hover:underline">Usuń model<span class="sr-only">: {{ $product->name }}</span></button>
                            @endif
                        </div>
                        <p class="text-[12px] leading-[1.5] text-hint">Plik GLB ze skanu (RealityScan, Polycam), najlepiej do 5 MB, w prawdziwej wielkości. Z modelem na karcie produktu pojawia się „Zobacz u siebie”.</p>
                        @foreach ($modelErrors as $message)
                            <p role="alert" class="text-[13px] text-error">{{ $message }}</p>
                        @endforeach
                    </div>
                    <div class="grid gap-4">
                        <x-shared::field name="name" :id="$formKey.'-name'" label="Nazwa" :value="$old('name', $product->name)" :bag="$formKey" maxlength="120" placeholder="np. Miska z odciskiem paproci" />
                        @if (session('duplicated'))
                            {{-- A fresh copy: the name is selected, so typing replaces „… (kopia)”. --}}
                            <span hidden x-data x-init="$nextTick(() => { const name = document.getElementById(@js($formKey.'-name')); name?.focus(); name?.select() })"></span>
                        @endif
                        <x-shared::field name="piece_name" :id="$formKey.'-piece-name'" label="Nazwa jednej sztuki" :value="$old('piece_name', $product->piece_name)" :bag="$formKey" maxlength="120" placeholder="np. Wazon"
                                         hint="Na certyfikacie i stronie kupionej sztuki — „Wazon” zamiast „Wazony”. Puste pole: zostanie nazwa produktu." />
                        <div class="min-w-0">
                            <label for="{{ $formKey }}-category" class="mb-1.5 block text-[13.5px] text-graphite">Rodzaj w sklepie</label>
                            <select id="{{ $formKey }}-category" name="category_id" @if ($bag->has('category_id')) aria-invalid="true" @endif class="field-control">
                                <option value="">Wybierz rodzaj</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}" @selected((int) $old('category_id', $product->category_id) === $category->id)>{{ $category->name }}</option>
                                @endforeach
                            </select>
                            {!! $error('category_id') !!}
                        </div>
                        <div class="min-w-0">
                            <label for="{{ $formKey }}-description" class="mb-1.5 block text-[13.5px] text-graphite">Opis</label>
                            <textarea id="{{ $formKey }}-description" name="description" rows="5" maxlength="2000" placeholder="z czego, jak powstaje, co z tym zrobisz"
                                      @if ($bag->has('description')) aria-invalid="true" @endif class="field-control resize-y leading-[1.6]">{{ $old('description', $product->description) }}</textarea>
                            {!! $error('description') !!}
                        </div>
                        {{-- In Kasia's words, below the product and on the page of every piece bought from it (MA-292). --}}
                        <div class="min-w-0">
                            <label for="{{ $formKey }}-story" class="mb-1.5 block text-[13.5px] text-graphite">Skąd ten pomysł <span class="text-hint">— nieobowiązkowe</span></label>
                            <textarea id="{{ $formKey }}-story" name="story" rows="3" maxlength="1500" aria-describedby="{{ $formKey }}-story-note" placeholder="co Cię zainspirowało, co chciałaś w tym zawrzeć"
                                      @if ($bag->has('story')) aria-invalid="true" @endif class="field-control resize-y leading-[1.6]">{{ $old('story', $product->story) }}</textarea>
                            @if ($bag->has('story'))
                                <p id="{{ $formKey }}-story-note" class="mt-1.5 text-[13px] text-error">{{ $bag->first('story') }}</p>
                            @else
                                <p id="{{ $formKey }}-story-note" class="{{ $note }}">Pod produktem w sklepie i na stronie każdej kupionej sztuki. Puste pole nie pokaże się na stronie.</p>
                            @endif
                        </div>
                    </div>
                </x-admin::tab-panel>

                {{-- Szczegóły: dimensions, and what the terms and the law want a buyer to know. --}}
                <x-admin::tab-panel name="details" class="grid gap-[18px]">
                    <div>
                        <h2 class="{{ $legend }}">Wymiary</h2>
                        <p class="{{ $lead }} mt-1">Wpisz tylko to, co pasuje. Puste pola nie pokażą się na stronie.</p>
                    </div>
                    <div class="grid grid-cols-2 gap-x-3.5 gap-y-3 md:grid-cols-3">
                        @foreach (Dimension::cases() as $dimension)
                            <label class="grid min-w-0 gap-1.5">
                                <span class="text-[13.5px] text-graphite">{{ $dimension->label() }}</span>
                                <span class="field-affix"><input name="dimensions[{{ $dimension->value }}]" value="{{ $dimensions[$dimension->value] ?? '' }}" inputmode="decimal" placeholder="—"
                                                                 @if ($bag->has('dimensions.'.$dimension->value)) aria-invalid="true" @endif><span>{{ $dimension->unit() }}</span></span>
                                {!! $error('dimensions.'.$dimension->value) !!}
                            </label>
                        @endforeach
                    </div>
                    <x-shared::field name="size_tolerance" :id="$formKey.'-tolerance'" label="Dopuszczalna różnica wymiarów" :value="$old('size_tolerance', $product->size_tolerance)" :bag="$formKey" maxlength="60"
                                     :placeholder="$defaultTolerance ? 'jak w Ustawieniach: '.$defaultTolerance : 'np. 0,5 cm'"
                                     :hint="$defaultTolerance ? 'Puste pole — obowiązuje różnica z Ustawień: '.$defaultTolerance.'.' : 'Puste pole nie pokaże się na stronie.'" />

                    <div>
                        <h2 class="{{ $sub }}">Prawo i pielęgnacja</h2>
                        <p class="{{ $lead }} mt-1">Tego wymaga regulamin i prawo. Klient zobaczy to na karcie produktu przed zakupem.</p>
                    </div>
                    <div class="min-w-0">
                        <label for="{{ $formKey }}-food" class="mb-1.5 block text-[13.5px] text-graphite">Kontakt z żywnością</label>
                        <select id="{{ $formKey }}-food" name="food_contact" aria-describedby="{{ $formKey }}-food-note" @if ($bag->has('food_contact')) aria-invalid="true" @endif class="field-control">
                            <option value="">Nie podaję</option>
                            @foreach (FoodContact::cases() as $option)
                                <option value="{{ $option->value }}" @selected($foodContact === $option->value)>{{ $option->option() }}</option>
                            @endforeach
                        </select>
                        @if ($bag->has('food_contact'))
                            <p id="{{ $formKey }}-food-note" class="mt-1.5 text-[13px] text-error">{{ $bag->first('food_contact') }}</p>
                        @else
                            <p id="{{ $formKey }}-food-note" class="{{ $note }}">„Tak” dopiero po badaniach szkliwa i wpisie do sanepidu. „Nie podaję” — nic nie pokaże się na stronie.</p>
                        @endif
                    </div>
                    <x-shared::field name="care_note" :id="$formKey.'-care'" label="Pielęgnacja" :value="$old('care_note', $product->care_note)" :bag="$formKey" maxlength="200"
                                     placeholder="np. Zmywarka tak, złoto tylko ręcznie" hint="Jedno zdanie na karcie produktu. Puste pole nie pokaże się na stronie." />
                    <x-shared::field name="deviation" :id="$formKey.'-deviation'" label="Cecha do osobnego potwierdzenia" :value="$old('deviation', $product->deviation)" :bag="$formKey" maxlength="160"
                                     placeholder="np. Nie do zmywarki ani mikrofalówki — złota krawędź"
                                     hint="Tylko to, czego nikt by się nie spodziewał. Klient potwierdzi to osobnym polem przy zamówieniu." />
                    <div class="min-w-0">
                        <label for="{{ $formKey }}-warnings" class="mb-1.5 block text-[13.5px] text-graphite">Ostrzeżenia — na karcie produktu i na certyfikacie</label>
                        <textarea id="{{ $formKey }}-warnings" name="safety_warnings" rows="2" maxlength="400" aria-describedby="{{ $formKey }}-warnings-note" placeholder="np. Nie stawiaj na ogniu ani na płycie grzewczej."
                                  @if ($bag->has('safety_warnings')) aria-invalid="true" @endif class="field-control resize-y leading-[1.6]">{{ $old('safety_warnings', $product->safety_warnings) }}</textarea>
                        @if ($bag->has('safety_warnings'))
                            <p id="{{ $formKey }}-warnings-note" class="mt-1.5 text-[13px] text-error">{{ $bag->first('safety_warnings') }}</p>
                        @else
                            <p id="{{ $formKey }}-warnings-note" class="{{ $note }}">Krótko, po polsku. Obok pokażą się dane producenta z Ustawień → Dane firmy.</p>
                        @endif
                    </div>
                    <label class="{{ $switchRow }}">
                        <input type="checkbox" name="is_exact_piece" value="1" @checked($exactPiece) class="peer sr-only">
                        <span aria-hidden="true" class="switch-track mt-px"></span>
                        <span>Zdjęcia pokazują dokładnie tę sztukę<span class="mt-0.5 block text-[12.5px] text-hint">Bez zaznaczenia strona napisze, że zdjęcia pokazują przykładową sztukę.</span></span>
                    </label>
                    <label class="{{ $switchRow }}">
                        <input type="checkbox" name="is_one_off" value="1" @checked($oneOff) class="peer sr-only">
                        <span aria-hidden="true" class="switch-track mt-px"></span>
                        <span>Unikat — każda sztuka jest jedna</span>
                    </label>
                </x-admin::tab-panel>

                {{-- Prezent i Google --}}
                <x-admin::tab-panel name="gift" class="grid gap-[18px]">
                    <div>
                        <h2 class="{{ $legend }}">„Szukam prezentu”</h2>
                        <p class="{{ $lead }} mt-1">Produkt pokaże się w podpowiedziach prezentów dla zaznaczonych okazji i osób.</p>
                    </div>
                    <fieldset class="min-w-0">
                        <legend class="mb-2 text-[13.5px] text-graphite">Na jaką okazję</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach (Occasion::cases() as $occasion)
                                <label class="{{ $chip }}"><input type="checkbox" name="occasions[]" value="{{ $occasion->value }}" @checked(in_array($occasion->value, $occasions, true)) class="sr-only">{{ $occasion->label() }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <fieldset class="min-w-0">
                        <legend class="mb-2 text-[13.5px] text-graphite">Dla kogo</legend>
                        <div class="flex flex-wrap gap-2">
                            @foreach (Recipient::cases() as $recipient)
                                <label class="{{ $chip }}"><input type="checkbox" name="recipients[]" value="{{ $recipient->value }}" @checked(in_array($recipient->value, $recipients, true)) class="sr-only">{{ $recipient->label() }}</label>
                            @endforeach
                        </div>
                    </fieldset>
                    <h2 class="{{ $sub }}">Zakupy Google — bezpłatne wyniki</h2>
                    <x-shared::seo-field name="seo_description" :id="$formKey.'-seo-description'" label="Opis dla Google" :limit="155" :max="255" :rows="2" :bag="$formKey"
                                         :value="$old('seo_description', $product->seo_description)"
                                         :placeholder="Seo::description($product->description) ?? 'z początku opisu, ucięty na granicy słowa'" />
                    <div class="min-w-0">
                        <label for="{{ $formKey }}-google-category" class="mb-1.5 block text-[13.5px] text-graphite">Rodzaj w Google</label>
                        <select id="{{ $formKey }}-google-category" name="google_category" aria-describedby="{{ $formKey }}-google-category-note" @if ($bag->has('google_category')) aria-invalid="true" @endif class="field-control">
                            <option value="">Niech Google dobierze sam</option>
                            @foreach (GoogleCategory::cases() as $option)
                                <option value="{{ $option->value }}" @selected($googleCategory === (string) $option->value)>{{ $option->label() }}</option>
                            @endforeach
                        </select>
                        @if ($bag->has('google_category'))
                            <p id="{{ $formKey }}-google-category-note" class="mt-1.5 text-[13px] text-error">{{ $bag->first('google_category') }}</p>
                        @else
                            <p id="{{ $formKey }}-google-category-note" class="{{ $note }}">Pomaga Google pokazać produkt osobom, które szukają właśnie tego.</p>
                        @endif
                    </div>
                    <label class="{{ $switchRow }}">
                        <input type="checkbox" name="show_in_google" value="1" @checked($showInGoogle) class="peer sr-only">
                        <span aria-hidden="true" class="switch-track mt-px"></span>
                        <span>Pokazuj w Zakupach Google<span class="mt-0.5 block text-[12.5px] text-hint">Trafi tam, gdy jest widoczny w sklepie i ma zdjęcie. Google zagląda do sklepu raz na dobę.</span></span>
                    </label>
                </x-admin::tab-panel>

                {{-- English: written here even while the English shop is off, so it waits ready. --}}
                <x-admin::tab-panel name="en" class="grid gap-[18px]">
                    @unless ($englishOn)
                        <p class="rounded-[4px] border border-line-strong bg-sand px-4 py-3 text-[13px] text-lead">Angielski sklep jeszcze nie działa — włączymy go razem ze sprzedażą do UE. To, co tu wpiszesz, poczeka.</p>
                    @endunless
                    <div>
                        <h2 class="{{ $legend }}">English</h2>
                        <p class="{{ $lead }} mt-1">{{ match (true) {
                            ! $english => 'Bez angielskiej nazwy produkt nie pokaże się w angielskim sklepie.',
                            $product->variants->contains(fn ($variant) => $variant->price('EUR') !== null) => 'Produkt jest w angielskim sklepie pod adresem /en/product/'.$english->slug,
                            default => 'Angielski tekst jest gotowy. Produkt pokaże się pod adresem /en/product/'.$english->slug.', gdy wpiszesz cenę w euro przy rozmiarze.',
                        } }}</p>
                    </div>
                    <div lang="en" class="grid gap-4 md:grid-cols-2">
                        @foreach ([['name', 'Nazwa po angielsku', 'e.g. Fern imprint bowl'], ['piece_name', 'Nazwa jednej sztuki po angielsku', 'e.g. Vase']] as [$field, $label, $placeholder])
                            <div class="min-w-0">
                                <label for="{{ $formKey }}-en-{{ str_replace('_', '-', $field) }}" lang="pl" class="mb-1.5 block text-[13.5px] text-graphite">{{ $label }}</label>
                                <input id="{{ $formKey }}-en-{{ str_replace('_', '-', $field) }}" name="en[{{ $field }}]" value="{{ $en($field) }}" maxlength="120" placeholder="{{ $placeholder }}"
                                       @if ($bag->has('en.'.$field)) aria-invalid="true" @endif class="field-control">
                                <div lang="pl">{!! $error('en.'.$field) !!}</div>
                            </div>
                        @endforeach
                    </div>
                    <h3 class="{{ $sub }}">Rozmiary i ceny w euro</h3>
                    <label class="{{ $switchRow }}" x-data>
                        <input type="checkbox" name="euro_from_rate" value="1" @checked($fromRate) class="peer sr-only" x-on:change="$root.closest('section').querySelectorAll('[data-euro]').forEach((input) => input.readOnly = $el.checked)">
                        <span aria-hidden="true" class="switch-track mt-px"></span>
                        <span>Cena w euro z kursu NBP
                            <span class="mt-0.5 block text-[12.5px] leading-[1.5] text-hint">
                                Liczy się sama z ceny w złotych po średnim kursie NBP{{ $euroMarkup ? ', z narzutem '.$euroMarkup.'%' : '' }}, w górę do końcówki ,90{{ $euroRate ? ' — dziś 239 zł to '.Money::input(ConvertEuroPrices::euro(23900, $euroRate->tenThousandths(), $euroMarkup)).' €' : '' }}.
                                @if ($euroRate)
                                    Kurs z {{ $euroRate->effective_on->format('d.m.Y') }}: {{ str_replace('.', ',', $euroRate->rate) }} zł.
                                @else
                                    Kursu jeszcze nie ma — ceny w euro pojawią się w ciągu godziny.
                                @endif
                                Narzut ustawiasz w <a href="{{ route('admin.products.settings') }}" class="underline underline-offset-[3px]">Ustawieniach produktów</a>.
                            </span>
                        </span>
                    </label>
                    <div class="grid gap-2">
                        @foreach ($rows as $index => $row)
                            <div lang="en" class="grid grid-cols-2 items-center gap-2.5 rounded-[14px] border border-divider bg-linen p-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)] md:border-0 md:bg-transparent md:p-0">
                                <span lang="pl" class="col-span-2 min-w-0 text-[13.5px] break-words text-lead md:col-span-1">{{ ($row['label'] ?? '') ?: 'Jedna cena' }}</span>
                                <label class="grid min-w-0 gap-1">
                                    <span lang="pl" class="text-[11.5px] text-label md:sr-only">Po angielsku</span>
                                    <input name="variants[{{ $index }}][label_en]" value="{{ $row['label_en'] ?? '' }}" placeholder="e.g. Small 12 cm" aria-label="Po angielsku: {{ ($row['label'] ?? '') ?: 'rozmiar '.($index + 1) }}"
                                           @if ($bag->has('variants.'.$index.'.label_en')) aria-invalid="true" @endif class="field-control field-compact">
                                </label>
                                <label class="grid min-w-0 gap-1">
                                    <span lang="pl" class="text-[11.5px] text-label md:sr-only">Cena w euro</span>
                                    <span class="field-affix"><input name="variants[{{ $index }}][price_eur]" value="{{ $row['price_eur'] ?? '' }}" inputmode="decimal" placeholder="—" data-euro @readonly($fromRate)
                                                                     aria-label="Cena w euro: {{ ($row['label'] ?? '') ?: 'rozmiar '.($index + 1) }}" @if ($bag->has('variants.'.$index.'.price_eur')) aria-invalid="true" @endif><span>€</span></span>
                                </label>
                                <label class="grid min-w-0 gap-1" x-show="promo" @unless ($promo) style="display: none" @endunless>
                                    <span lang="pl" class="text-[11.5px] text-label md:sr-only">€ przed obniżką</span>
                                    <span class="field-affix"><input name="variants[{{ $index }}][compare_at_eur]" value="{{ $row['compare_at_eur'] ?? '' }}" inputmode="decimal" placeholder="€ przed obniżką" data-euro @readonly($fromRate) x-bind:disabled="! promo" @disabled(! $promo)
                                                                     aria-label="Cena w euro przed obniżką: {{ ($row['label'] ?? '') ?: 'rozmiar '.($index + 1) }}" @if ($bag->has('variants.'.$index.'.compare_at_eur')) aria-invalid="true" @endif><span>€</span></span>
                                </label>
                                <div lang="pl" class="col-span-2 md:col-span-4">{!! $error('variants.'.$index.'.label_en') !!}{!! $error('variants.'.$index.'.price_eur') !!}{!! $error('variants.'.$index.'.compare_at_eur') !!}</div>
                            </div>
                        @endforeach
                    </div>
                    <p class="{{ $note }} mt-0">Rozmiar bez ceny w euro nie pokaże się po angielsku — polska wersja zostaje bez zmian. Nowy rozmiar dostanie tu swój wiersz po zapisaniu.</p>
                    <div lang="en" class="grid gap-4">
                        @foreach ([
                            ['description', 'Opis po angielsku', 'textarea', 2000, 'what it’s made of, how it’s made, what to do with it'],
                            ['story', 'Skąd ten pomysł po angielsku', 'textarea', 1500, 'what inspired it, what you wanted it to hold'],
                            ['care_note', 'Pielęgnacja po angielsku', 'input', 200, 'e.g. Dishwasher safe, gold by hand only'],
                            ['deviation', 'Cecha do osobnego potwierdzenia po angielsku', 'input', 160, 'e.g. Not for dishwasher or microwave — gold rim'],
                            ['size_tolerance', 'Dopuszczalna różnica wymiarów po angielsku', 'input', 60, 'e.g. 0.5 cm'],
                            ['safety_warnings', 'Ostrzeżenia po angielsku', 'textarea', 400, 'e.g. Do not place on a flame or a hob.'],
                        ] as [$field, $label, $kind, $max, $placeholder])
                            <div class="min-w-0">
                                <label for="{{ $formKey }}-en-{{ str_replace('_', '-', $field) }}" lang="pl" class="mb-1.5 block text-[13.5px] text-graphite">{{ $label }}</label>
                                @if ($kind === 'textarea')
                                    <textarea id="{{ $formKey }}-en-{{ str_replace('_', '-', $field) }}" name="en[{{ $field }}]" rows="{{ in_array($field, ['description', 'story'], true) ? 4 : 2 }}" maxlength="{{ $max }}" placeholder="{{ $placeholder }}"
                                              @if ($bag->has('en.'.$field)) aria-invalid="true" @endif class="field-control resize-y leading-[1.6]">{{ $en($field) }}</textarea>
                                @else
                                    <input id="{{ $formKey }}-en-{{ str_replace('_', '-', $field) }}" name="en[{{ $field }}]" value="{{ $en($field) }}" maxlength="{{ $max }}" placeholder="{{ $placeholder }}"
                                           @if ($bag->has('en.'.$field)) aria-invalid="true" @endif class="field-control">
                                @endif
                                <div lang="pl">{!! $error('en.'.$field) !!}</div>
                            </div>
                        @endforeach
                        <div lang="pl">
                            <x-shared::seo-field name="en[seo_description]" error-key="en.seo_description" :id="$formKey.'-en-seo-description'" label="Opis dla Google po angielsku"
                                                 :limit="155" :max="255" :rows="2" :bag="$formKey" :value="$en('seo_description')"
                                                 :placeholder="Seo::description($english?->description) ?? 'from the start of the description, cut at a word'" />
                        </div>
                    </div>
                </x-admin::tab-panel>

                {{-- The save row sticks to the bottom on glass once something changes (MA-251). Enter saves: the first button is „Zapisz w sklepie”. --}}
                <div class="mt-[22px] flex flex-wrap items-center justify-end gap-2.5">
                    <x-shared::button size="md" class="order-last max-md:flex-1">Zapisz w sklepie</x-shared::button>
                    @if ($next)
                        <x-shared::button name="next" value="{{ $next }}" variant="secondary" size="md" class="max-md:hidden">Zapisz i następny →</x-shared::button>
                    @endif
                </div>
            </form>

            {{-- On a phone the dots on the tabs tell what is missing; the preview would only lengthen every tab. --}}
            <aside aria-label="Podgląd i braki" class="hidden min-w-0 gap-4 md:sticky md:top-20 md:grid md:flex-[0_1_296px]">
                <section class="rounded-[4px] border border-line bg-sand p-[18px]">
                    <h2 class="mb-3 text-[10.5px] tracking-[0.24em] text-label uppercase">Tak to zobaczą klienci</h2>
                    <div class="relative aspect-[4/5] max-w-full overflow-hidden rounded-[18px] bg-linen">
                        @if ($cover)
                            <img src="{{ $cover->getAvailableUrl(['thumb']) }}" alt="" loading="lazy" style="{{ FocalPoint::style($cover) }}" class="size-full object-cover">
                        @else
                            <span class="absolute inset-0 grid place-items-center rounded-[18px] border border-dashed border-line-strong p-5 text-center text-[12.5px] text-label">Bez zdjęcia produkt wygląda w sklepie pusto</span>
                        @endif
                        <span class="glass absolute bottom-3 left-3 rounded-full px-3.5 py-1.5 font-serif text-[17px] tabular-nums">{{ $price }}</span>
                    </div>
                    <p class="mt-3 font-serif text-[23px] leading-[1.15] font-light">{{ $product->name }}</p>
                    <p class="mt-1 text-[10.5px] tracking-[0.2em] text-label uppercase">{{ $product->category->name }}</p>
                    @unless ($product->is_published)
                        <p class="mt-2.5 text-[12.5px] text-label">Teraz ukryty — klienci go nie widzą.</p>
                    @endunless
                </section>
                <section class="rounded-[4px] border border-line bg-sand p-[18px]">
                    <h2 class="mb-2 flex justify-between text-[10.5px] tracking-[0.24em] text-label uppercase">Gotowość <span class="tracking-[0.06em] text-lead">{{ $done }} z {{ count($ready) }}</span></h2>
                    <div class="mb-2.5 h-1.5 overflow-hidden rounded-full bg-sand-dark" role="img" aria-label="Gotowe {{ $done }} z {{ count($ready) }}">
                        <span class="block h-full rounded-full bg-ink" style="width: {{ round($done / count($ready) * 100) }}%"></span>
                    </div>
                    <ul class="grid">
                        @foreach ($ready as [$label, $ok, $detail, $target, $field, $must])
                            <li class="flex min-h-10 items-center gap-2.5 border-t border-divider text-[13px] text-lead first:border-t-0">
                                @if ($ok)
                                    <span aria-hidden="true" class="grid size-5 flex-none place-items-center rounded-full border border-success-line bg-success-soft text-[11px] text-success">✓</span>
                                @else
                                    <span aria-hidden="true" @class(['size-5 flex-none rounded-full border border-dashed', 'border-brown bg-alert' => $must, 'border-line-strong' => ! $must])></span>
                                @endif
                                <span class="min-w-0 flex-1">{{ $label }}<span class="block text-[11.5px] text-hint">{{ $detail }}</span></span>
                                @unless ($ok)
                                    <button type="button" x-on:click="show(@js($target)); @if ($field) $nextTick(() => document.getElementById('{{ $field }}')?.focus()) @endif"
                                            class="min-h-10 text-[13px] whitespace-nowrap text-brown underline decoration-line-strong underline-offset-[3px] hover:text-ink">Dodaj<span class="sr-only"> {{ $label }}</span> →</button>
                                @endunless
                            </li>
                        @endforeach
                    </ul>
                </section>
            </aside>
        </div>

        {{-- Photo and 3D model forms live outside the product form; the buttons on them point here with form="…". --}}
        <form id="model-dodaj" method="post" action="{{ route('admin.products.model.store', $product) }}" enctype="multipart/form-data" class="hidden">@csrf</form>
        <form id="model-usun" method="post" action="{{ route('admin.products.model.destroy', $product) }}" class="hidden"
              x-data x-on:submit="confirm('Usunąć model 3D? Przycisk „Zobacz u siebie” zniknie z karty produktu.') || $event.preventDefault()">@csrf @method('DELETE')</form>
        <form id="zdjecia-dodaj" method="post" action="{{ route('admin.products.photos.store', $product) }}" enctype="multipart/form-data" class="hidden">@csrf</form>
        @foreach ($photos as $photo)
            <form id="zdjecie-{{ $photo->id }}-lewo" method="post" action="{{ route('admin.products.photos.move', [$product, $photo]) }}" class="hidden">@csrf<input type="hidden" name="kierunek" value="lewo"></form>
            <form id="zdjecie-{{ $photo->id }}-prawo" method="post" action="{{ route('admin.products.photos.move', [$product, $photo]) }}" class="hidden">@csrf<input type="hidden" name="kierunek" value="prawo"></form>
            <form id="zdjecie-{{ $photo->id }}-usun" method="post" action="{{ route('admin.products.photos.destroy', [$product, $photo]) }}" class="hidden"
                  x-data x-on:submit="confirm('Usunąć to zdjęcie? Nie da się tego cofnąć.') || $event.preventDefault()">@csrf @method('DELETE')</form>
        @endforeach
      </x-admin::tabs>
    </div>
</x-admin::layout>
