@use('App\Modules\Catalog\Enums\Dimension')
@use('App\Modules\Catalog\Enums\FoodContact')
@use('App\Modules\Catalog\Enums\GoogleCategory')
@use('App\Modules\Catalog\Enums\Occasion')
@use('App\Modules\Catalog\Enums\Recipient')
@use('App\Modules\Shared\Support\Money')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    // Several forms share one page, so old input and errors only count for the form they came from.
    $bag = $errors->getBag($formKey);
    $mine = old('form') === $formKey;
    $old = fn (string $key, mixed $default = null) => $mine ? old($key, $default) : $default;

    $rows = $mine
        ? array_values((array) old('variants', []))
        : ($product?->variants->map(fn ($variant) => [
            'id' => $variant->id,
            'label' => $variant->label,
            'label_en' => $variant->translation('en')?->label,
            'price' => Money::input($variant->price_gross),
            'compare_at' => $variant->compare_at_price === null ? '' : Money::input($variant->compare_at_price),
            'price_eur' => ($eur = $variant->price('EUR')) === null ? '' : Money::input($eur),
            'compare_at_eur' => ($eurBefore = $variant->compareAtPrice('EUR')) === null ? '' : Money::input($eurBefore),
            'stock' => $variant->stock,
            'sent_by_post' => $variant->sent_by_post,
        ])->all() ?? []);
    // A voucher goes out as a PDF by e-mail; a size ticked „pocztą” is printed and posted, so its buyer chooses a delivery.
    $isVoucher = (bool) $product?->isVoucher();
    // Spare rows for new sizes; left empty, they are not saved.
    $rows = [...$rows, ...array_fill(0, max(1, 3 - count($rows)), ['id' => null, 'label' => '', 'label_en' => '', 'price' => '', 'compare_at' => '', 'price_eur' => '', 'compare_at_eur' => '', 'stock' => ''])];

    $dimensions = (array) $old('dimensions', $product?->dimensions ?? []);
    $occasions = (array) $old('occasions', $product?->occasions ?? []);
    $recipients = (array) $old('recipients', $product?->recipients ?? []);
    $published = $mine ? (bool) old('is_published') : ($product?->is_published ?? true);
    $oneOff = $mine ? (bool) old('is_one_off') : (bool) $product?->is_one_off;
    $exactPiece = $mine ? (bool) old('is_exact_piece') : (bool) $product?->is_exact_piece;
    $foodContact = (string) $old('food_contact', $product?->food_contact?->value);
    $googleCategory = (string) $old('google_category', $product?->google_category?->value);
    $showInGoogle = $mine ? (bool) old('show_in_google') : ($product?->show_in_google ?? true);
    $defaultTolerance = $settings->get('size_tolerance');
    // The English version, written here even while the English shop is still switched off.
    $english = $product?->translation('en');
    $en = fn (string $field) => $old('en.'.$field, $english?->{$field});
    // Open when something is written or wrong in it, so a mistake never hides in a closed block.
    $englishOpen = filled($english) || collect($bag->keys())->contains(fn (string $key) => str_starts_with($key, 'en.'));

    $input = 'min-w-0 rounded-[4px] border bg-white text-[15px] text-ink placeholder:text-hint focus:border-ink';
    $legend = 'mb-2.5 text-[11px] tracking-[0.14em] text-hint uppercase';
    $chip = 'flex cursor-pointer items-center rounded-full border border-line bg-cream px-[13px] py-1.5 text-[12.5px] text-lead has-checked:border-ink has-checked:bg-ink has-checked:text-linen has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy';
    $error = fn (string $key) => $bag->has($key) ? '<p class="mt-1.5 text-[13px] text-error">'.e($bag->first($key)).'</p>' : '';
@endphp
<form method="post" action="{{ $product ? route('admin.products.update', $product) : route('admin.products.store') }}" @unless ($product) enctype="multipart/form-data" @endunless novalidate class="grid gap-[13px]">
    @csrf
    @if ($product)
        @method('PUT')
    @endif
    <input type="hidden" name="form" value="{{ $formKey }}">

    @if ($bag->any())
        <p role="alert" class="rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">
            Popraw zaznaczone pola, żeby zapisać zmiany.
            @if ((int) $old('photos_chosen') > 0)
                Zdjęcia wybierz jeszcze raz — po błędzie przeglądarka ich nie pamięta.
            @endif
        </p>
    @endif

    @unless ($product)
        @php
            $photoErrors = collect($bag->get('photos'))->merge(collect($bag->get('photos.*'))->flatten())->unique();
        @endphp
        <div x-data="photoPicker(@js($photoLimits))" class="min-w-0">
            <label class="relative block cursor-pointer rounded-[4px] border border-dashed border-line-strong bg-linen p-[22px] text-center transition duration-300 hover:border-ink hover:bg-sand-dark active:scale-[.98] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                <span class="block text-[14px] text-lead" x-text="previews.length ? 'Dodaj kolejne zdjęcia' : 'Wybierz zdjęcia z telefonu albo komputera'">Wybierz zdjęcia z telefonu albo komputera</span>
                <span class="mt-1 block text-[12.5px] text-hint">JPG lub PNG, kilka naraz — pierwsze będzie okładką</span>
                <input type="file" name="photos[]" x-ref="photos" accept="image/jpeg,image/png,image/webp" multiple x-on:change="add($el)" class="sr-only">
            </label>
            <input type="hidden" name="photos_chosen" x-bind:value="previews.length">
            <div x-show="previews.length" class="mt-2.5 flex flex-wrap gap-2">
                <template x-for="(url, index) in previews" x-bind:key="url">
                    <div x-bind:class="index === 0 ? 'border-ink' : 'border-line'" class="relative h-[70px] w-[58px] rounded-[4px] border bg-line-soft">
                        <img x-bind:src="url" x-bind:alt="index === 0 ? 'Okładka' : 'Zdjęcie ' + (index + 1)" width="58" height="70" class="size-full rounded-[3px] object-cover">
                        <button type="button" x-on:click="remove(index)" x-bind:aria-label="'Nie dodawaj zdjęcia ' + (index + 1)"
                                class="absolute top-[3px] right-[3px] grid size-5 place-items-center rounded-full bg-ink/75 text-[11px] leading-none text-linen transition-colors duration-300 after:absolute after:-inset-3 hover:bg-error">×</button>
                    </div>
                </template>
            </div>
            <p role="alert" x-text="error" class="mt-1.5 text-[13px] text-error empty:hidden"></p>
            @foreach ($photoErrors as $message)
                <p class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
            @endforeach
        </div>
    @endunless

    <x-shared::field name="name" :id="$formKey.'-name'" label="Nazwa" :value="$old('name', $product?->name)" :bag="$formKey" placeholder="np. Miska z odciskiem paproci" />

    <div class="min-w-0">
        <label for="{{ $formKey }}-category" class="mb-1.5 block text-[13.5px] text-graphite">Rodzaj — kategoria w sklepie</label>
        <select id="{{ $formKey }}-category" name="category_id" @class([$input, 'w-full px-4 py-[13px]', 'border-error' => $bag->has('category_id'), 'border-line' => ! $bag->has('category_id')])>
            <option value="">Wybierz rodzaj</option>
            @foreach ($categories as $category)
                <option value="{{ $category->id }}" @selected((int) $old('category_id', $product?->category_id) === $category->id)>{{ $category->name }}</option>
            @endforeach
        </select>
        {!! $error('category_id') !!}
    </div>

    <fieldset class="rounded-[4px] border border-sand-dark bg-linen px-4 pt-3 pb-4">
        <legend class="{{ $legend }} mb-0 px-1">Rozmiary i ceny</legend>
        <div class="grid gap-2.5">
            @foreach ($rows as $index => $row)
                <div class="flex flex-wrap items-center gap-2.5">
                    <input type="hidden" name="variants[{{ $index }}][id]" value="{{ $row['id'] ?? '' }}">
                    <input name="variants[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" aria-label="Rozmiar {{ $index + 1 }}"
                           placeholder="{{ $index === 0 ? 'np. Mały 12 cm' : 'kolejny rozmiar' }}"
                           @class([$input, 'flex-[1_1_140px] px-3 py-2.5', 'border-error' => $bag->has('variants.'.$index.'.label'), 'border-line' => ! $bag->has('variants.'.$index.'.label')])>
                    <input name="variants[{{ $index }}][label_en]" value="{{ $row['label_en'] ?? '' }}" lang="en" aria-label="Rozmiar {{ $index + 1 }} po angielsku"
                           placeholder="{{ $index === 0 ? 'po angielsku, np. Small 12 cm' : 'po angielsku' }}"
                           @class([$input, 'flex-[1_1_120px] px-3 py-2.5', 'border-error' => $bag->has('variants.'.$index.'.label_en'), 'border-line' => ! $bag->has('variants.'.$index.'.label_en')])>
                    <span class="flex items-center gap-1.5">
                        <input name="variants[{{ $index }}][price]" value="{{ $row['price'] ?? '' }}" inputmode="decimal" aria-label="Cena, rozmiar {{ $index + 1 }}" placeholder="cena"
                               @class([$input, 'w-[90px] px-3 py-2.5 text-right', 'border-error' => $bag->has('variants.'.$index.'.price'), 'border-line' => ! $bag->has('variants.'.$index.'.price')])>
                        <span class="text-[13px] text-label">zł</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <input name="variants[{{ $index }}][compare_at]" value="{{ $row['compare_at'] ?? '' }}" inputmode="decimal" aria-label="Cena przed obniżką, rozmiar {{ $index + 1 }}" placeholder="przed obniżką"
                               @class([$input, 'w-[118px] px-3 py-2.5 text-right', 'border-error' => $bag->has('variants.'.$index.'.compare_at'), 'border-line' => ! $bag->has('variants.'.$index.'.compare_at')])>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <input name="variants[{{ $index }}][price_eur]" value="{{ $row['price_eur'] ?? '' }}" inputmode="decimal" aria-label="Cena w euro, rozmiar {{ $index + 1 }}" placeholder="w euro"
                               @class([$input, 'w-[90px] px-3 py-2.5 text-right', 'border-error' => $bag->has('variants.'.$index.'.price_eur'), 'border-line' => ! $bag->has('variants.'.$index.'.price_eur')])>
                        <span class="text-[13px] text-label">€</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <input name="variants[{{ $index }}][compare_at_eur]" value="{{ $row['compare_at_eur'] ?? '' }}" inputmode="decimal" aria-label="Cena w euro przed obniżką, rozmiar {{ $index + 1 }}" placeholder="€ przed obniżką"
                               @class([$input, 'w-[118px] px-3 py-2.5 text-right', 'border-error' => $bag->has('variants.'.$index.'.compare_at_eur'), 'border-line' => ! $bag->has('variants.'.$index.'.compare_at_eur')])>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <input name="variants[{{ $index }}][stock]" value="{{ $row['stock'] ?? '' }}" inputmode="numeric" aria-label="Sztuk na półce, rozmiar {{ $index + 1 }}" placeholder="—"
                               @class([$input, 'w-[64px] px-2.5 py-2.5 text-right', 'border-error' => $bag->has('variants.'.$index.'.stock'), 'border-line' => ! $bag->has('variants.'.$index.'.stock')])>
                        <span class="text-[12.5px] text-label">szt.</span>
                    </span>
                    @if ($isVoucher)
                        <label class="flex min-h-11 items-center gap-1.5 text-[12.5px] text-label">
                            <input type="checkbox" name="variants[{{ $index }}][sent_by_post]" value="1" @checked(! empty($row['sent_by_post'])) class="size-4 accent-ink"> pocztą
                        </label>
                    @endif
                    @if (! empty($row['id']))
                        <label class="flex min-h-11 items-center gap-1.5 text-[12.5px] text-label">
                            <input type="checkbox" name="variants[{{ $index }}][remove]" value="1" class="size-4 accent-error"> usuń
                        </label>
                    @endif
                </div>
                {!! $error('variants.'.$index.'.label') !!}{!! $error('variants.'.$index.'.label_en') !!}{!! $error('variants.'.$index.'.price') !!}{!! $error('variants.'.$index.'.compare_at') !!}{!! $error('variants.'.$index.'.price_eur') !!}{!! $error('variants.'.$index.'.compare_at_eur') !!}{!! $error('variants.'.$index.'.stock') !!}
            @endforeach
        </div>
        {!! $error('variants') !!}
        <p class="mt-2.5 text-[12.5px] leading-[1.5] text-hint">Puste wiersze się nie zapiszą. Jedna cena nie potrzebuje nazwy rozmiaru. Pole „szt.” zostaw puste, jeśli nie liczysz sztuk — przy zerze produkt sam znika ze sklepu. „Przed obniżką” wpisz tylko przy promocji: karta produktu przekreśli tę cenę, gdy obniżysz cenę, i sama poda najniższą cenę z 30 dni.</p>
        <p class="mt-1.5 text-[12.5px] leading-[1.5] text-hint">Cenę w euro widać tylko w angielskiej wersji sklepu. Rozmiar bez niej nie pokazuje się po angielsku — polska wersja zostaje bez zmian. Wpisz ją ręcznie: nic się nie przelicza po kursie.</p>
        @if ($isVoucher)
            <p class="mt-1.5 text-[12.5px] leading-[1.5] text-hint">Voucher idzie mailem w PDF, bez kosztów dostawy. Zaznacz „pocztą” przy rozmiarze, który drukujesz i wysyłasz — wtedy zamówienie zapyta o dostawę.</p>
        @endif
    </fieldset>

    <fieldset>
        <legend class="{{ $legend }} mb-1">Wymiary — nieobowiązkowe</legend>
        <p class="mb-2.5 text-[12.5px] text-hint">Wpisz tylko to, co pasuje. Puste pola nie pokażą się na stronie.</p>
        <div class="flex flex-wrap gap-2">
            @foreach (Dimension::cases() as $dimension)
                <label class="flex items-center gap-1.5 rounded-[4px] border border-line bg-white px-2.5 py-1.5 text-[12px] text-label">
                    {{ $dimension->label() }}
                    <input name="dimensions[{{ $dimension->value }}]" value="{{ $dimensions[$dimension->value] ?? '' }}" inputmode="decimal" placeholder="—"
                           class="w-[58px] min-w-0 rounded-[3px] border border-sand-dark bg-linen px-1.5 py-1 text-right text-[15px] text-ink focus:border-ink">
                    {{ $dimension->unit() }}
                </label>
            @endforeach
        </div>
        @foreach (Dimension::cases() as $dimension)
            {!! $error('dimensions.'.$dimension->value) !!}
        @endforeach
    </fieldset>

    <div class="min-w-0">
        <label for="{{ $formKey }}-description" class="mb-1.5 block text-[13.5px] text-graphite">Opis</label>
        <textarea id="{{ $formKey }}-description" name="description" rows="4" placeholder="z czego, jak powstaje, co z tym zrobisz"
                  @class([$input, 'w-full resize-y px-4 py-[13px] leading-[1.6]', 'border-error' => $bag->has('description'), 'border-line' => ! $bag->has('description')])>{{ $old('description', $product?->description) }}</textarea>
        {!! $error('description') !!}
    </div>

    <x-shared::field name="care_note" :id="$formKey.'-care'" label="Pielęgnacja — zdanie na karcie produktu" :value="$old('care_note', $product?->care_note)" :bag="$formKey"
                     placeholder="np. Zmywarka tak, złoto tylko ręcznie" hint="Puste pole nie pokaże się na stronie" />

    <fieldset class="rounded-[4px] border border-sand-dark bg-linen px-4 pt-3 pb-4">
        <legend class="{{ $legend }} mb-0 px-1">Zanim ktoś kupi — tego wymaga regulamin i prawo</legend>
        <div class="grid gap-3">
            <div class="min-w-0">
                <label for="{{ $formKey }}-food" class="mb-1.5 block text-[13.5px] text-graphite">Kontakt z żywnością</label>
                <select id="{{ $formKey }}-food" name="food_contact" aria-describedby="{{ $formKey }}-food-note"
                        @class([$input, 'w-full px-4 py-[13px]', 'border-error' => $bag->has('food_contact'), 'border-line' => ! $bag->has('food_contact')])>
                    <option value="">Nie podaję</option>
                    @foreach (FoodContact::cases() as $option)
                        <option value="{{ $option->value }}" @selected($foodContact === $option->value)>{{ $option->option() }}</option>
                    @endforeach
                </select>
                @if ($bag->has('food_contact'))
                    <p id="{{ $formKey }}-food-note" class="mt-1.5 text-[13px] text-error">{{ $bag->first('food_contact') }}</p>
                @else
                    <p id="{{ $formKey }}-food-note" class="mt-1.5 text-[12.5px] text-hint">„Tak” wybierz dopiero po badaniach szkliwa i wpisie do sanepidu. „Nie podaję” — nic nie pokaże się na stronie.</p>
                @endif
            </div>
            <x-shared::field name="deviation" :id="$formKey.'-deviation'" label="Cecha do osobnego potwierdzenia" :value="$old('deviation', $product?->deviation)" :bag="$formKey" maxlength="160"
                             placeholder="np. Nie do zmywarki ani mikrofalówki — złota krawędź"
                             hint="Tylko to, czego nikt by się nie spodziewał. Klientka potwierdzi to osobnym polem przy zamówieniu." />
            <x-shared::field name="size_tolerance" :id="$formKey.'-tolerance'" label="Dopuszczalna różnica wymiarów" :value="$old('size_tolerance', $product?->size_tolerance)" :bag="$formKey" maxlength="60"
                             :placeholder="$defaultTolerance ? 'jak w Ustawieniach: '.$defaultTolerance : 'np. 0,5 cm'"
                             :hint="$defaultTolerance ? 'Puste pole — obowiązuje różnica z Ustawień: '.$defaultTolerance.'.' : 'Puste pole nie pokaże się na stronie.'" />
            <div class="min-w-0">
                <label for="{{ $formKey }}-warnings" class="mb-1.5 block text-[13.5px] text-graphite">Ostrzeżenia — na karcie produktu i na certyfikacie</label>
                <textarea id="{{ $formKey }}-warnings" name="safety_warnings" rows="2" maxlength="400" aria-describedby="{{ $formKey }}-warnings-note"
                          placeholder="np. Nie stawiaj na ogniu ani na płycie grzewczej."
                          @class([$input, 'w-full resize-y px-4 py-[13px] leading-[1.6]', 'border-error' => $bag->has('safety_warnings'), 'border-line' => ! $bag->has('safety_warnings')])>{{ $old('safety_warnings', $product?->safety_warnings) }}</textarea>
                @if ($bag->has('safety_warnings'))
                    <p id="{{ $formKey }}-warnings-note" class="mt-1.5 text-[13px] text-error">{{ $bag->first('safety_warnings') }}</p>
                @else
                    <p id="{{ $formKey }}-warnings-note" class="mt-1.5 text-[12.5px] text-hint">Krótko, po polsku. Obok pokażą się dane producenta z Ustawień → Dane firmy.</p>
                @endif
            </div>
            <label class="flex min-h-11 items-start gap-2.5 text-[14px] text-graphite">
                <input type="checkbox" name="is_exact_piece" value="1" @checked($exactPiece) class="mt-[3px] size-4 flex-none accent-ink">
                <span>Ta sztuka — zdjęcia pokazują dokładnie rzecz, którą wyślę<span class="mt-0.5 block text-[12.5px] text-hint">Bez zaznaczenia strona napisze, że zdjęcia pokazują przykładową sztukę.</span></span>
            </label>
        </div>
    </fieldset>

    <details @if ($englishOpen) open @endif class="group rounded-[4px] border border-sand-dark bg-linen px-4 py-3">
        <summary class="flex min-h-11 cursor-pointer list-none items-center justify-between gap-3 text-[14px] text-graphite">
            <span>Wersja angielska — nieobowiązkowa
                <span class="mt-0.5 block text-[12.5px] text-hint">{{ match (true) {
                    ! $english => 'Bez angielskiej nazwy produkt nie pokaże się w angielskim sklepie.',
                    $product->variants->contains(fn ($variant) => $variant->price('EUR') !== null) => 'Produkt jest w angielskim sklepie pod adresem /en/product/'.$english->slug,
                    default => 'Angielski tekst jest gotowy. Produkt pokaże się pod adresem /en/product/'.$english->slug.', gdy wpiszesz cenę w euro przy rozmiarze.',
                } }}</span>
            </span>
            <span aria-hidden="true" class="text-[18px] leading-none text-label transition-transform duration-300 group-open:rotate-45">+</span>
        </summary>
        <div lang="en" class="mt-3 grid gap-3">
            <p lang="pl" class="text-[12.5px] leading-[1.5] text-hint">Pisz tak jak po polsku — konkretnie, w pierwszej osobie. Nazwy rozmiarów po angielsku wpisz wyżej, obok polskich. Puste pole nie pokaże się na angielskiej stronie, a tekst nie podstawi się po polsku.</p>
            @foreach ([
                ['name', 'Nazwa po angielsku', 'input', 120, 'e.g. Fern imprint bowl'],
                ['description', 'Opis po angielsku', 'textarea', 2000, 'what it’s made of, how it’s made, what to do with it'],
                ['care_note', 'Pielęgnacja po angielsku', 'input', 200, 'e.g. Dishwasher safe, gold by hand only'],
                ['deviation', 'Cecha do osobnego potwierdzenia po angielsku', 'input', 160, 'e.g. Not for dishwasher or microwave — gold rim'],
                ['size_tolerance', 'Dopuszczalna różnica wymiarów po angielsku', 'input', 60, 'e.g. 0.5 cm'],
                ['safety_warnings', 'Ostrzeżenia po angielsku', 'textarea', 400, 'e.g. Do not place on a flame or a hob.'],
            ] as [$field, $label, $kind, $max, $placeholder])
                <div class="min-w-0">
                    <label for="{{ $formKey }}-en-{{ $field }}" lang="pl" class="mb-1.5 block text-[13.5px] text-graphite">{{ $label }}</label>
                    @if ($kind === 'textarea')
                        <textarea id="{{ $formKey }}-en-{{ $field }}" name="en[{{ $field }}]" rows="{{ $field === 'description' ? 4 : 2 }}" maxlength="{{ $max }}" placeholder="{{ $placeholder }}"
                                  @class([$input, 'w-full resize-y px-4 py-[13px] leading-[1.6]', 'border-error' => $bag->has('en.'.$field), 'border-line' => ! $bag->has('en.'.$field)])>{{ $en($field) }}</textarea>
                    @else
                        <input id="{{ $formKey }}-en-{{ $field }}" name="en[{{ $field }}]" value="{{ $en($field) }}" maxlength="{{ $max }}" placeholder="{{ $placeholder }}"
                               @class([$input, 'w-full px-4 py-[13px]', 'border-error' => $bag->has('en.'.$field), 'border-line' => ! $bag->has('en.'.$field)])>
                    @endif
                    <div lang="pl">{!! $error('en.'.$field) !!}</div>
                </div>
            @endforeach
        </div>
    </details>

    @if ($product?->getMedia('images')->isNotEmpty())
        <fieldset>
            <legend class="{{ $legend }} mb-1">Opisy zdjęć — dla Google i czytników ekranu</legend>
            <p class="mb-2.5 text-[12.5px] leading-[1.5] text-hint">Napisz, co widać: kolor, detal, np. „Piaskowy kubek z turkusowym wnętrzem”. Puste pole — strona opisze zdjęcie nazwą produktu.</p>
            <div class="grid gap-2">
                @foreach ($product->getMedia('images') as $photo)
                    <div class="flex items-center gap-2.5">
                        <img src="{{ $photo->getAvailableUrl(['thumb']) }}" alt="" width="38" height="46" class="h-[46px] w-[38px] flex-none rounded-[3px] bg-line-soft object-cover">
                        <input name="photo_alts[{{ $photo->id }}]" value="{{ $old('photo_alts.'.$photo->id, $photo->getCustomProperty('alt')) }}" maxlength="160"
                               aria-label="Opis zdjęcia {{ $loop->iteration }}" placeholder="{{ $product->name }}"
                               @class([$input, 'flex-1 px-3 py-2.5', 'border-error' => $bag->has('photo_alts.'.$photo->id), 'border-line' => ! $bag->has('photo_alts.'.$photo->id)])>
                    </div>
                    {!! $error('photo_alts.'.$photo->id) !!}
                @endforeach
            </div>
        </fieldset>
    @endif

    <fieldset>
        <legend class="{{ $legend }}">Na jaką okazję — pokaże się w „Szukam prezentu”</legend>
        <div class="flex flex-wrap gap-[7px]">
            @foreach (Occasion::cases() as $occasion)
                <label class="{{ $chip }}">
                    <input type="checkbox" name="occasions[]" value="{{ $occasion->value }}" @checked(in_array($occasion->value, $occasions, true)) class="sr-only">{{ $occasion->label() }}
                </label>
            @endforeach
        </div>
    </fieldset>

    <fieldset>
        <legend class="{{ $legend }}">Dla kogo — też w „Szukam prezentu”</legend>
        <div class="flex flex-wrap gap-[7px]">
            @foreach (Recipient::cases() as $recipient)
                <label class="{{ $chip }}">
                    <input type="checkbox" name="recipients[]" value="{{ $recipient->value }}" @checked(in_array($recipient->value, $recipients, true)) class="sr-only">{{ $recipient->label() }}
                </label>
            @endforeach
        </div>
    </fieldset>

    <fieldset class="rounded-[4px] border border-sand-dark bg-linen px-4 pt-3 pb-4">
        <legend class="{{ $legend }} mb-0 px-1">Zakupy Google — bezpłatne wyniki</legend>
        <div class="grid gap-3">
            <div class="min-w-0">
                <label for="{{ $formKey }}-google-category" class="mb-1.5 block text-[13.5px] text-graphite">Rodzaj w Google</label>
                <select id="{{ $formKey }}-google-category" name="google_category" aria-describedby="{{ $formKey }}-google-category-note"
                        @class([$input, 'w-full px-4 py-[13px]', 'border-error' => $bag->has('google_category'), 'border-line' => ! $bag->has('google_category')])>
                    <option value="">Niech Google dobierze sam</option>
                    @foreach (GoogleCategory::cases() as $option)
                        <option value="{{ $option->value }}" @selected($googleCategory === (string) $option->value)>{{ $option->label() }}</option>
                    @endforeach
                </select>
                @if ($bag->has('google_category'))
                    <p id="{{ $formKey }}-google-category-note" class="mt-1.5 text-[13px] text-error">{{ $bag->first('google_category') }}</p>
                @else
                    <p id="{{ $formKey }}-google-category-note" class="mt-1.5 text-[12.5px] text-hint">Pomaga Google pokazać produkt osobom, które szukają właśnie tego.</p>
                @endif
            </div>
            <label class="flex min-h-11 items-start gap-2.5 text-[14px] text-graphite">
                <input type="checkbox" name="show_in_google" value="1" @checked($showInGoogle) class="mt-[3px] size-4 flex-none accent-ink">
                <span>Pokazuj w Zakupach Google<span class="mt-0.5 block text-[12.5px] text-hint">Trafi tam, gdy jest widoczny w sklepie i ma zdjęcie. Google zagląda do sklepu raz na dobę.</span></span>
            </label>
        </div>
    </fieldset>

    <div class="flex flex-wrap gap-x-6">
        <label class="flex min-h-11 items-center gap-2.5 text-[14px] text-graphite">
            <input type="checkbox" name="is_published" value="1" @checked($published) class="size-4 accent-ink"> Widoczny w sklepie
        </label>
        <label class="flex min-h-11 items-center gap-2.5 text-[14px] text-graphite">
            <input type="checkbox" name="is_one_off" value="1" @checked($oneOff) class="size-4 accent-ink"> Unikat — każda sztuka jest jedna
        </label>
    </div>

    <button class="rounded-full bg-ink p-4 text-[14.5px] text-linen transition duration-300 hover:bg-navy active:scale-[.97]">{{ $product ? 'Zapisz zmiany w sklepie' : 'Opublikuj w sklepie' }}</button>
</form>
