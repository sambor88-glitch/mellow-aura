@use('App\Modules\Gifts\Http\Requests\Admin\SaveBundleRequest')
@use('App\Modules\Gifts\Models\Bundle')
@use('App\Modules\Shared\Support\Money')
@php
    // Several forms share one page, so old input and errors only count for the form they came from.
    $bag = $errors->getBag($formKey);
    $mine = old('form') === $formKey;
    $old = fn (string $key, mixed $default = null) => $mine ? old($key, $default) : $default;

    $parts = $mine ? array_values((array) old('parts', [])) : ($bundle?->items->pluck('product_variant_id')->all() ?? []);
    $parts = array_map(fn (mixed $part) => (string) $part, array_pad(array_slice($parts, 0, SaveBundleRequest::MAX_PARTS), SaveBundleRequest::MAX_PARTS, ''));
    $published = $mine ? (bool) old('is_published') : ($bundle?->is_published ?? true);
    $prices = $products->flatMap(fn ($product) => $product->variants->mapWithKeys(fn ($variant) => ['v'.$variant->id => $variant->price_gross]));
    $discount = (string) $old('discount_percent', $bundle?->discount_percent ?? 10);
    // The same preview counted on the server, so the card reads right before the script starts.
    $chosen = array_values(array_filter($parts));
    $full = (int) collect($chosen)->sum(fn (string $id) => $prices['v'.$id] ?? 0);
    $price = Bundle::discounted($full, min(90, max(0, (int) $discount)));

    $input = 'min-w-0 rounded-[4px] border bg-white text-[15px] text-ink placeholder:text-hint focus:border-ink';
    $error = fn (string $key) => $bag->has($key) ? '<p class="mt-1.5 text-[13px] text-error">'.e($bag->first($key)).'</p>' : '';
@endphp
{{-- The price preview counts like the shop: the parts' sum minus the discount, rounded to whole złoty. --}}
<form method="post" action="{{ $bundle ? route('admin.gifts.bundles.update', $bundle) : route('admin.gifts.bundles.store') }}" novalidate class="grid gap-[13px]"
      x-data="{
          prices: @js($prices),
          parts: @js($parts),
          discount: @js($discount),
          get full() { return this.parts.reduce((sum, id) => sum + (this.prices['v' + id] ?? 0), 0) },
          get chosen() { return this.parts.filter(id => id !== '').length },
          get price() { const percent = Math.min(90, Math.max(0, parseInt(this.discount, 10) || 0)); return Math.floor((this.full * (100 - percent) + 5000) / 10000) * 100 },
      }">
    @csrf
    @if ($bundle)
        @method('PUT')
    @endif
    <input type="hidden" name="form" value="{{ $formKey }}">

    @if ($bag->any())
        <p role="alert" class="rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać zestaw.</p>
    @endif

    <x-shared::field name="name" :id="$formKey.'-name'" label="Nazwa" :value="$old('name', $bundle?->name)" :bag="$formKey" placeholder="np. Poranek we dwoje" />

    <div class="min-w-0">
        <label for="{{ $formKey }}-description" class="mb-1.5 block text-[13.5px] text-graphite">Opis</label>
        <textarea id="{{ $formKey }}-description" name="description" rows="3" placeholder="co jest w środku i dla kogo"
                  @class([$input, 'w-full resize-y px-4 py-[13px] leading-[1.6]', 'border-error' => $bag->has('description'), 'border-line' => ! $bag->has('description')])>{{ $old('description', $bundle?->description) }}</textarea>
        {!! $error('description') !!}
    </div>

    <fieldset class="min-w-0">
        <legend class="mb-2 text-[13.5px] text-graphite">W zestawie — od dwóch do czterech rzeczy</legend>
        <div class="grid gap-2">
            @foreach ($parts as $index => $part)
                <select name="parts[]" x-model="parts[{{ $index }}]" aria-label="Rzecz {{ $index + 1 }} w zestawie"
                        @class([$input, 'w-full px-3 py-2.5 text-[14px]', 'border-error' => $bag->has('parts.'.$index), 'border-line' => ! $bag->has('parts.'.$index)])>
                    <option value="">{{ $index < 2 ? 'Wybierz rzecz ze sklepu' : 'bez kolejnej rzeczy' }}</option>
                    @foreach ($products as $product)
                        <optgroup label="{{ $product->name }}{{ $product->is_published ? '' : ' — ukryty w sklepie' }}">
                            @foreach ($product->variants as $variant)
                                <option value="{{ $variant->id }}" @selected($part === (string) $variant->id)>{{ $product->name }}{{ $variant->label ? ' · '.$variant->label : '' }} — {{ Money::format($variant->price_gross) }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
                {!! $error('parts.'.$index) !!}
            @endforeach
        </div>
        {!! $error('parts') !!}
    </fieldset>

    <div class="flex flex-wrap items-end gap-x-6 gap-y-3">
        <div>
            <label for="{{ $formKey }}-discount" class="mb-1.5 block text-[13.5px] text-graphite">Rabat</label>
            <span class="flex items-center gap-1.5">
                <input id="{{ $formKey }}-discount" name="discount_percent" value="{{ $discount }}" x-model="discount" inputmode="numeric" maxlength="3"
                       @if ($bag->has('discount_percent')) aria-invalid="true" aria-describedby="{{ $formKey }}-discount-error" @endif
                       @class([$input, 'min-h-11 w-[72px] px-2.5 text-right', 'border-error' => $bag->has('discount_percent'), 'border-line' => ! $bag->has('discount_percent')])>
                <span aria-hidden="true" class="text-[13px] text-label">%</span>
            </span>
        </div>
        <label class="flex min-h-11 items-center gap-2.5 text-[14px] text-graphite">
            <input type="checkbox" name="is_published" value="1" @checked($published) class="size-4 accent-ink"> Widoczny na stronie zestawów
        </label>
    </div>
    @if ($bag->has('discount_percent'))
        <p id="{{ $formKey }}-discount-error" class="-mt-1.5 text-[13px] text-error">{{ $bag->first('discount_percent') }}</p>
    @endif

    <p class="rounded-[4px] bg-sand-dark px-4 py-3 text-[13.5px] text-lead" aria-live="polite">
        <span x-show="chosen >= 2" @if (count($chosen) < 2) x-cloak @endif>Tak to zobaczą klienci: <strong class="font-medium text-ink" x-text="$store.cart.format(price)">{{ Money::format($price) }}</strong>, osobno <span x-text="$store.cart.format(full)">{{ Money::format($full) }}</span></span>
        <span x-show="chosen < 2" @if (count($chosen) >= 2) x-cloak @endif>Cena pokaże się, gdy wybierzesz dwie rzeczy.</span>
    </p>

    <button class="rounded-full bg-ink p-4 text-[14.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">{{ $bundle ? 'Zapisz zestaw' : 'Dodaj zestaw na stronę' }}</button>
</form>
