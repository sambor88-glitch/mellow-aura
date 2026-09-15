@use('App\Modules\Catalog\Enums\Dimension')
@use('App\Modules\Catalog\Enums\Occasion')
@use('App\Modules\Catalog\Enums\Recipient')
@use('App\Modules\Shared\Support\Money')
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
            'price' => Money::input($variant->price_gross),
            'stock' => $variant->stock,
        ])->all() ?? []);
    // Spare rows for new sizes; left empty, they are not saved.
    $rows = [...$rows, ...array_fill(0, max(1, 3 - count($rows)), ['id' => null, 'label' => '', 'price' => '', 'stock' => ''])];

    $dimensions = (array) $old('dimensions', $product?->dimensions ?? []);
    $occasions = (array) $old('occasions', $product?->occasions ?? []);
    $recipients = (array) $old('recipients', $product?->recipients ?? []);
    $published = $mine ? (bool) old('is_published') : ($product?->is_published ?? true);
    $oneOff = $mine ? (bool) old('is_one_off') : (bool) $product?->is_one_off;

    $input = 'min-w-0 rounded-[4px] border bg-white text-[15px] text-ink placeholder:text-hint focus:border-ink';
    $legend = 'mb-2.5 text-[11px] tracking-[0.14em] text-hint uppercase';
    $chip = 'flex cursor-pointer items-center rounded-full border border-line bg-cream px-[13px] py-1.5 text-[12.5px] text-lead has-checked:border-ink has-checked:bg-ink has-checked:text-linen has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy';
    $error = fn (string $key) => $bag->has($key) ? '<p class="mt-1.5 text-[13px] text-error">'.e($bag->first($key)).'</p>' : '';
@endphp
<form method="post" action="{{ $product ? route('admin.products.update', $product) : route('admin.products.store') }}" novalidate class="grid gap-[13px]">
    @csrf
    @if ($product)
        @method('PUT')
    @endif
    <input type="hidden" name="form" value="{{ $formKey }}">

    @if ($bag->any())
        <p role="alert" class="rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać zmiany.</p>
    @endif

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
                    <span class="flex items-center gap-1.5">
                        <input name="variants[{{ $index }}][price]" value="{{ $row['price'] ?? '' }}" inputmode="decimal" aria-label="Cena, rozmiar {{ $index + 1 }}" placeholder="cena"
                               @class([$input, 'w-[90px] px-3 py-2.5 text-right', 'border-error' => $bag->has('variants.'.$index.'.price'), 'border-line' => ! $bag->has('variants.'.$index.'.price')])>
                        <span class="text-[13px] text-label">zł</span>
                    </span>
                    <span class="flex items-center gap-1.5">
                        <input name="variants[{{ $index }}][stock]" value="{{ $row['stock'] ?? '' }}" inputmode="numeric" aria-label="Sztuk na półce, rozmiar {{ $index + 1 }}" placeholder="—"
                               @class([$input, 'w-[64px] px-2.5 py-2.5 text-right', 'border-error' => $bag->has('variants.'.$index.'.stock'), 'border-line' => ! $bag->has('variants.'.$index.'.stock')])>
                        <span class="text-[12.5px] text-label">szt.</span>
                    </span>
                    @if (! empty($row['id']))
                        <label class="flex min-h-11 items-center gap-1.5 text-[12.5px] text-label">
                            <input type="checkbox" name="variants[{{ $index }}][remove]" value="1" class="size-4 accent-error"> usuń
                        </label>
                    @endif
                </div>
                {!! $error('variants.'.$index.'.label') !!}{!! $error('variants.'.$index.'.price') !!}{!! $error('variants.'.$index.'.stock') !!}
            @endforeach
        </div>
        {!! $error('variants') !!}
        <p class="mt-2.5 text-[12.5px] leading-[1.5] text-hint">Puste wiersze się nie zapiszą. Jedna cena nie potrzebuje nazwy rozmiaru. Pole „szt.” zostaw puste, jeśli nie liczysz sztuk — przy zerze produkt sam znika ze sklepu.</p>
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
