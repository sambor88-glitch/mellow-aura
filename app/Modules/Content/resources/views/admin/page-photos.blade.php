{{--
    Zdjęcia stron (MA-316): a tab for each page, the places on it as thumbnails, and one place at a time to change.
    Saving comes back to the same place (#key), and a mistake opens its tab and its place.
--}}
@use('Illuminate\Support\Str')
@php
    $card = 'scroll-mt-24 rounded-[4px] border border-line bg-cream px-[18px] py-5 md:px-[22px] md:py-6';
    $input = 'min-h-11 w-full min-w-0 rounded-[4px] border bg-white px-3 text-[14.5px] text-ink placeholder:text-hint focus:border-ink';
    $button = 'mt-3 min-h-11 w-full rounded-full bg-ink px-4 text-[14px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]';
    $quiet = 'min-h-11 text-[12.5px] text-label hover:text-ink';
    $tabs = $pages->mapWithKeys(fn ($rows, $page) => [Str::slug($page) => ['label' => $page, 'count' => count($rows)]])->all();
@endphp
<x-admin::layout title="Zdjęcia stron" lead="Każde zdjęcie na stronach poza sklepem. Stuknij zdjęcie, żeby wgrać swoje, przesunąć kadr albo zdjąć je ze strony. Zmiany widać od razu.">
  <x-admin::tabs :tabs="$tabs" label="Strony">
    @foreach ($pages as $page => $rows)
        @php
            $keys = collect($rows)->map(fn ($row) => $row['photo']->key)->values()->all();
            // A place with a mistake opens by itself; otherwise the browser opens the place from #key after saving.
            $mistake = collect($keys)->first(fn ($key) => $errors->getBag('zdjecie-'.$key)->any());
        @endphp
        <x-admin::tab-panel :name="Str::slug($page)">
          <div x-data="{ open: @js($mistake) }"
               x-init="const key = decodeURIComponent(location.hash.slice(1)); if (@js($keys).includes(key)) open = key"
               x-effect="if (open) $nextTick(() => document.getElementById(open)?.scrollIntoView({ block: 'nearest' }))">
            <ul x-show="open === null" @if ($mistake) style="display: none" @endif
                class="grid grid-cols-2 gap-3 md:grid-cols-[repeat(auto-fill,minmax(190px,1fr))] md:gap-[18px]">
                @foreach ($rows as ['place' => $place, 'photo' => $photo])
                    <li class="min-w-0">
                        <button type="button" x-on:click="open = @js($photo->key)" aria-controls="{{ $photo->key }}"
                                class="group block w-full rounded-[4px] border border-line bg-cream p-2 text-left transition-colors hover:border-ink">
                            @if ($photo->exists())
                                <img src="{{ $photo->url }}" alt="" loading="lazy" style="object-position: {{ $photo->focus['x'] ?? 50 }}% {{ $photo->focus['y'] ?? 50 }}%"
                                     class="block aspect-[4/3] w-full rounded-[3px] bg-line-soft object-cover">
                            @else
                                <span class="grid aspect-[4/3] w-full place-items-center rounded-[3px] border border-dashed border-line-strong bg-sand-dark px-2 text-center text-[12px] text-hint">puste miejsce</span>
                            @endif
                            <span class="mt-2 line-clamp-2 px-0.5 text-[13px] leading-[1.35] text-ink">{{ $place['label'] }}</span>
                            <span class="mt-0.5 block px-0.5 text-[12px] text-brown group-hover:text-ink">{{ $photo->exists() ? 'Zmień' : 'Dodaj zdjęcie' }} →</span>
                        </button>
                    </li>
                @endforeach
            </ul>

                @foreach ($rows as ['place' => $place, 'photo' => $photo])
                    @php
                        $bag = $errors->getBag('zdjecie-'.$photo->key);
                        $point = fn (string $field, array $saved) => $bag->any() ? old($field, $saved) : $saved;
                        $focus = $point('focus', $photo->focus);
                        $phoneFocus = $photo->phoneFocus === null ? null : $point('focus_phone', $photo->phoneFocus);
                        $decorative = $place['decorative'] ?? false;
                        $linksProduct = $place['product'] ?? false;
                        $productId = $bag->any() ? old('product_id') : $photo->productId;
                        // The hero starts at 82%; a step of 5 would round it to 80% on the first save.
                        $step = isset($place['focus']) ? 1 : 5;
                    @endphp
                    <article id="{{ $photo->key }}" x-show="open === @js($photo->key)" @if ($mistake !== $photo->key) style="display: none" @endif
                             @if ($bag->any()) data-error @endif class="{{ $card }} max-w-[640px]">
                        <button type="button" x-on:click="open = null" class="-mt-2 mb-1 inline-flex min-h-11 items-center gap-1.5 text-[13px] text-lead hover:text-ink">← Wszystkie zdjęcia: {{ $page }}</button>
                        <h3 class="mb-3 text-[15px] leading-[1.45] text-ink">{{ $place['label'] }}</h3>

                        @if ($photo->exists())
                            <form method="post" action="{{ route('admin.page-photos.update', $photo->key) }}" novalidate>
                                @csrf
                                @method('PUT')
                                <x-shared::focus-picker name="focus" :src="$photo->url" :x="$focus['x'] ?? 50" :y="$focus['y'] ?? 50"
                                                        label="Kadr: {{ $place['label'] }}" :frames="$place['frames']" :step="$step" />
                                @if ($phoneFocus !== null)
                                    <p class="mt-4 mb-1.5 text-[13px] text-lead">Na telefonie ramka jest węższa i wyższa — tu ustawiasz kadr osobno.</p>
                                    <x-shared::focus-picker name="focus_phone" :src="$photo->url" :x="$phoneFocus['x'] ?? 50" :y="$phoneFocus['y'] ?? 50"
                                                            label="Kadr na telefonie: {{ $place['label'] }}" :frames="['aspect-[2/3]' => 'na starcie', 'aspect-[9/19]' => 'otwarta']" :step="$step" />
                                @endif
                                @unless ($decorative)
                                    <label class="mt-3 block">
                                        <span class="mb-1 block text-[12px] text-muted">Co widać na zdjęciu — dla osób, które go nie widzą</span>
                                        <input type="text" name="alt" maxlength="160" value="{{ $bag->any() ? old('alt') : $photo->alt }}"
                                               placeholder="{{ ! $photo->isReplaced ? 'Zostaw puste, żeby został obecny opis' : 'Np. Kubek z wbijanym napisem na stole' }}"
                                               class="{{ $input }} {{ $bag->has('alt') ? 'border-error' : 'border-line' }}">
                                    </label>
                                @endunless
                                @if ($linksProduct)
                                    <label class="mt-3 block">
                                        <span class="mb-1 block text-[12px] text-muted">Prowadzi do produktu</span>
                                        <select name="product_id" class="{{ $input }} {{ $bag->has('product_id') ? 'border-error' : 'border-line' }}">
                                            <option value="">Bez linku — samo zdjęcie</option>
                                            @foreach ($products as $product)
                                                <option value="{{ $product->id }}" @selected((string) $productId === (string) $product->id)>{{ $product->name }}{{ $product->is_published ? '' : ' (ukryty w sklepie)' }}</option>
                                            @endforeach
                                        </select>
                                        <span class="mt-1 block text-[12px] text-hint">Gdy produkt zniknie ze sklepu albo się wyprzeda, zdjęcie zostaje, tylko bez linku.</span>
                                    </label>
                                @endif
                                @foreach (collect($bag->getMessages())->except('photo')->flatten()->unique() as $message)
                                    <p role="alert" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                @endforeach
                                <button class="{{ $button }}">{{ $linksProduct ? 'Zapisz' : 'Zapisz kadr' }}</button>
                            </form>
                        @else
                            <div class="grid aspect-[4/3] place-items-center rounded-[3px] border border-dashed border-line-strong bg-sand-dark px-4 text-center text-[13px] text-hint">Brak zdjęcia — to miejsce na stronie jest puste</div>
                        @endif

                        <form method="post" action="{{ route('admin.page-photos.store', $photo->key) }}" enctype="multipart/form-data" novalidate x-data="{ sending: false }" class="mt-4">
                            @csrf
                            <label class="block cursor-pointer rounded-[4px] border border-dashed border-line-strong bg-linen p-4 text-center transition duration-300 hover:border-ink hover:bg-sand-dark active:scale-[.98] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                <span class="block text-[14px] text-lead" x-text="sending ? 'Wgrywam zdjęcie…' : @js($photo->exists() ? 'Wgraj swoje zamiast tego' : 'Wybierz zdjęcie z telefonu albo komputera')">{{ $photo->exists() ? 'Wgraj swoje zamiast tego' : 'Wybierz zdjęcie z telefonu albo komputera' }}</span>
                                <span class="mt-1 block text-[12.5px] text-hint">JPG, PNG albo WebP, do 15 MB</span>
                                <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" class="sr-only"
                                       x-on:change="if ($el.files.length) { sending = true; $el.form.requestSubmit() }">
                            </label>
                            @if ($bag->has('photo'))
                                <p role="alert" class="mt-1.5 text-[13px] text-error">{{ $bag->first('photo') }}</p>
                            @endif
                            <noscript><button class="{{ $button }}">Wgraj wybrane zdjęcie</button></noscript>
                        </form>

                        <div class="mt-1 flex flex-wrap justify-between gap-x-4">
                            @if ($photo->exists())
                                <form method="post" action="{{ route('admin.page-photos.destroy', $photo->key) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button class="{{ $quiet }}">Zdejmij zdjęcie ze strony</button>
                                </form>
                            @endif
                            @unless ($photo->isDefault)
                                <form method="post" action="{{ route('admin.page-photos.restore', $photo->key) }}">
                                    @csrf
                                    <button class="{{ $quiet }}">{{ $photo->isReplaced ? 'Przywróć pierwsze zdjęcie' : 'Przywróć pierwszy kadr' }}</button>
                                </form>
                            @endunless
                        </div>
                    </article>
                @endforeach
          </div>
        </x-admin::tab-panel>
    @endforeach
  </x-admin::tabs>
</x-admin::layout>
