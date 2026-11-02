@use('App\Modules\Catalog\Enums\VariantKind')
@php
    // A new product in five fields (MA-307): photos, name, kind, price and pieces. The rest waits in the card.
    $formKey = 'nowy-produkt';
    $bag = $errors->getBag($formKey);
    $mine = old('form') === $formKey;
    $rows = $mine ? array_values((array) old('variants', [])) : [];
    $rows = $rows ?: [['id' => null, 'label' => '', 'price' => '', 'stock' => '']];
    $many = count($rows) > 1 || filled($rows[0]['label'] ?? null);
    $photoErrors = collect($bag->get('photos'))->merge(collect($bag->get('photos.*'))->flatten())->unique();
    $seg = 'min-h-10 rounded-full border border-line-strong bg-cream px-4 text-[13px] text-lead hover:border-ink hover:text-ink aria-pressed:border-ink aria-pressed:bg-ink aria-pressed:text-linen';
@endphp
<x-admin::layout title="Nowy produkt" lead="Na start wystarczy to, co niżej. Opis, wymiary, okazje i Google dopiszesz potem w karcie produktu.">
    <a href="{{ route('admin.products.index') }}" class="-mt-2 mb-3 inline-flex min-h-11 items-center gap-1.5 text-[13px] text-lead hover:text-ink">← Produkty</a>

    <form method="post" action="{{ route('admin.products.store') }}" enctype="multipart/form-data" novalidate data-unsaved-name="Nowy produkt"
          x-data="{ many: @js($many), promo: false, next: {{ count($rows) }} }"
          class="grid max-w-[720px] gap-[18px] rounded-[4px] border border-line bg-cream px-3.5 pt-[18px] pb-3.5 md:px-[26px] md:pt-[26px] md:pb-[18px]">
        @csrf
        <input type="hidden" name="form" value="{{ $formKey }}">
        <input type="hidden" name="show_in_google" value="1">
        <input type="hidden" name="variant_kind" value="{{ $many ? VariantKind::Sizes->value : VariantKind::Options->value }}" x-bind:value="many ? @js(VariantKind::Sizes->value) : @js(VariantKind::Options->value)">

        @if ($bag->any())
            <p role="alert" class="rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">
                Popraw zaznaczone pola, żeby zapisać produkt.
                @if ((int) old('photos_chosen') > 0)
                    Zdjęcia wybierz jeszcze raz — po błędzie przeglądarka ich nie pamięta.
                @endif
            </p>
        @endif

        <div x-data="photoPicker(@js($photoLimits))" class="min-w-0">
            <span class="mb-1.5 block text-[13.5px] text-graphite">Zdjęcia</span>
            <label class="relative block cursor-pointer rounded-[14px] border border-dashed border-line-strong bg-linen p-[22px] text-center transition duration-300 hover:border-ink hover:bg-sand-dark active:scale-[.98] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                <span class="block text-[14px] text-lead" x-text="previews.length ? 'Dodaj kolejne zdjęcia' : 'Zrób zdjęcie albo wybierz z telefonu czy komputera'">Zrób zdjęcie albo wybierz z telefonu czy komputera</span>
                <span class="mt-1 block text-[12.5px] text-hint">JPG lub PNG, kilka naraz — pierwsze będzie okładką</span>
                <input type="file" name="photos[]" x-ref="photos" accept="image/jpeg,image/png,image/webp" multiple x-on:change="add($el)" class="sr-only">
            </label>
            <input type="hidden" name="photos_chosen" x-bind:value="previews.length">
            <div x-show="previews.length" x-cloak class="mt-2.5 flex flex-wrap gap-2">
                <template x-for="(url, index) in previews" x-bind:key="url">
                    <div x-bind:class="index === 0 ? 'border-ink' : 'border-line'" class="relative h-[72px] w-[58px] rounded-[8px] border bg-line-soft">
                        <img x-bind:src="url" x-bind:alt="index === 0 ? 'Okładka' : 'Zdjęcie ' + (index + 1)" width="58" height="72" class="size-full rounded-[7px] object-cover">
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

        <x-shared::field name="name" :id="$formKey.'-name'" label="Nazwa" :value="$mine ? old('name') : null" :bag="$formKey" maxlength="120" placeholder="np. Miska z odciskiem paproci" />

        <div class="min-w-0">
            <label for="{{ $formKey }}-category" class="mb-1.5 block text-[13.5px] text-graphite">Rodzaj w sklepie</label>
            <select id="{{ $formKey }}-category" name="category_id" @if ($bag->has('category_id')) aria-invalid="true" @endif class="field-control">
                <option value="">Wybierz rodzaj</option>
                @foreach ($categories as $category)
                    <option value="{{ $category->id }}" @selected($mine && (int) old('category_id') === $category->id)>{{ $categoryTree->label($category) }}</option>
                @endforeach
            </select>
            @if ($bag->has('category_id'))
                <p class="mt-1.5 text-[13px] text-error">{{ $bag->first('category_id') }}</p>
            @endif
        </div>

        <div class="grid gap-2.5">
            <span id="{{ $formKey }}-price-mode" class="text-[13.5px] text-graphite">Cena</span>
            <div role="group" aria-labelledby="{{ $formKey }}-price-mode" class="flex flex-wrap gap-2">
                <button type="button" aria-pressed="{{ $many ? 'false' : 'true' }}" x-bind:aria-pressed="(! many).toString()" class="{{ $seg }}"
                        x-on:click="many = false; [...$refs.rows.children].slice(1).forEach((row) => row.remove()); $refs.rows.querySelector('[name$=\'[label]\']').value = ''">Jedna cena</button>
                <button type="button" aria-pressed="{{ $many ? 'true' : 'false' }}" x-bind:aria-pressed="many.toString()" class="{{ $seg }}" x-on:click="many = true">Kilka rozmiarów</button>
            </div>
            <div x-bind:class="{ 'one-price': ! many }" @class(['grid gap-2', 'one-price' => ! $many])>
                <div aria-hidden="true" class="size-grid size-head px-0.5 text-[10.5px] tracking-[0.12em] text-label uppercase">
                    <span>Rozmiar</span><span>Cena</span><span class="size-promo">Przed obniżką</span><span>Na półce</span><span></span>
                </div>
                <div x-ref="rows" class="grid gap-2">
                    @foreach ($rows as $index => $row)
                        @include('catalog::admin.products._size-row', ['index' => $index, 'row' => $row, 'bag' => $bag, 'first' => $index === 0])
                    @endforeach
                </div>
                <template x-ref="row">@include('catalog::admin.products._size-row', ['index' => '__i__', 'row' => [], 'bag' => $bag])</template>
            </div>
            <button type="button" x-show="many" @unless ($many) x-cloak @endunless
                    x-on:click="$refs.rows.insertAdjacentHTML('beforeend', $refs.row.innerHTML.replaceAll('__i__', next++)); $nextTick(() => $refs.rows.lastElementChild.querySelector('input:not([type=hidden])').focus())"
                    class="flex min-h-[46px] w-full items-center justify-center rounded-[14px] border border-dashed border-line-strong bg-linen text-[13.5px] text-lead hover:border-ink hover:text-ink">+ Dodaj rozmiar</button>
            @if ($bag->has('variants'))
                <p class="text-[13px] text-error">{{ $bag->first('variants') }}</p>
            @endif
            <p class="text-[12.5px] text-hint">Puste „Na półce” — nie liczysz sztuk. Puste wiersze się nie zapiszą.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <x-shared::button name="is_published" value="1" size="md">Opublikuj w sklepie</x-shared::button>
            <x-shared::button name="is_published" value="0" variant="secondary" size="md">Zapisz jako ukryty</x-shared::button>
        </div>
    </form>
</x-admin::layout>
