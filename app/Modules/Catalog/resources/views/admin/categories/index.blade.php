@use('App\Modules\Catalog\Http\Requests\Admin\SaveShopShortcutsRequest')
@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\Shared\Support\Seo')
{{--
    Kategorie (MA-318): a list of the shelves with what each still lacks, one shelf open at a time with „Zapisz i następna”,
    and the captions under the shop's shortcut cards in a tab of their own.
--}}
@php
    $card = 'scroll-mt-24 rounded-[4px] border border-line bg-cream px-[18px] py-5 md:p-[22px]';
    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-4 py-[13px] text-[15px] leading-[1.6] text-ink placeholder:text-hint focus:border-ink';
    $keys = $categories->map(fn ($category) => 'kategoria-'.$category->id)->values()->all();
    $mistake = collect($keys)->first(fn ($key) => $errors->getBag($key)->any());
@endphp
<x-admin::layout title="Kategorie" lead="Co Google pokazuje dla każdej półki w sklepie i krótki wstęp nad jej produktami. Puste pole — strona złoży tytuł i opis sama.">
  <x-admin::tabs :tabs="['polki' => ['label' => 'Półki w sklepie', 'count' => $categories->count()], 'skroty' => 'Skróty pod produktami']" label="Części ekranu kategorii">
    <x-admin::tab-panel name="skroty">
    <div class="grid max-w-[880px] gap-[22px]">
        @php
            $bag = $errors->getBag('skroty');
            $english = Locales::isEnabled('en');
        @endphp
        <form id="skroty" method="post" action="{{ route('admin.categories.shortcuts') }}" novalidate class="{{ $card }}">
            @csrf
            @method('PUT')
            <div class="mb-1.5 flex flex-wrap items-baseline justify-between gap-3">
                <h2 class="font-serif text-[23px]">Skróty pod produktami</h2>
                <a href="{{ route('shop.index') }}" target="_blank" rel="noopener" class="text-[13px]">/sklep — „Zrobię dla Ciebie”</a>
            </div>
            <p class="mb-4 text-[13.5px] text-muted">Krótki podpis pod nazwą każdej karty. Puste pole — karta pokaże samą nazwę.</p>
            @if ($bag->any())
                <p role="alert" data-error class="mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać.</p>
            @endif
            <div class="grid gap-3.5">
                @foreach ($shortcutNotes as $label => $note)
                    <div @class(['grid gap-3', 'sm:grid-cols-2' => $english])>
                        @foreach ($english ? ['pl', 'en'] : ['pl'] as $locale)
                            @php
                                $field = $locale.'.'.$label;
                            @endphp
                            <div class="min-w-0">
                                <label for="skrot-{{ $locale }}-{{ $label }}" class="mb-1.5 block text-[13.5px] text-graphite">{{ __('catalog::shop.shortcut_'.$label, [], 'pl') }}{{ $locale === 'en' ? ' — po angielsku' : '' }}</label>
                                <input id="skrot-{{ $locale }}-{{ $label }}" name="{{ $locale }}[{{ $label }}]" value="{{ old($field, $note[$locale]) }}" maxlength="{{ SaveShopShortcutsRequest::MAX }}" @if ($locale === 'en') lang="en" @endif
                                       @class([$input, 'border-error' => $bag->has($field), 'border-line' => ! $bag->has($field)])>
                                @if ($bag->has($field))
                                    <p class="mt-1.5 text-[13px] text-error">{{ $bag->first($field) }}</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endforeach
            </div>
            <button class="mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zapisz podpisy</button>
        </form>
    </div>
    </x-admin::tab-panel>

    <x-admin::tab-panel name="polki">
    <div class="grid max-w-[880px] gap-[22px]" x-data="{ open: @js($mistake) }"
         x-init="const key = decodeURIComponent(location.hash.slice(1)); if (@js($keys).includes(key)) open = key"
         x-effect="if (open) $nextTick(() => document.getElementById(open)?.scrollIntoView({ block: 'nearest' }))">
        <ul x-show="open === null" @if ($mistake) style="display: none" @endif class="grid gap-2">
            @foreach ($categories as $category)
                @php
                    $google = filled($category->seo_title) && filled($category->seo_description);
                @endphp
                <li>
                    <button type="button" x-on:click="open = @js('kategoria-'.$category->id)" aria-controls="kategoria-{{ $category->id }}"
                            class="flex min-h-14 w-full flex-wrap items-center gap-x-4 gap-y-1 rounded-[4px] border border-line bg-cream px-[18px] py-3 text-left transition-colors hover:border-ink">
                        <span class="min-w-0 flex-[1_1_200px]">
                            <span class="block text-[15px] text-ink">{{ $category->name }}</span>
                            <span class="block text-[12.5px] text-label">produktów: {{ $category->products_count }}</span>
                        </span>
                        <span class="flex flex-wrap gap-1.5 text-[12px]">
                            <span @class(['rounded-full px-2.5 py-1', 'bg-success-soft text-success' => $google, 'bg-linen text-label' => ! $google])>{{ $google ? 'Google ✓' : 'Google: złoży sama' }}</span>
                            <span @class(['rounded-full px-2.5 py-1', 'bg-success-soft text-success' => filled($category->intro), 'bg-linen text-label' => blank($category->intro)])>{{ filled($category->intro) ? 'wstęp ✓' : 'bez wstępu' }}</span>
                        </span>
                        <span aria-hidden="true" class="text-[13px] text-brown">Otwórz →</span>
                    </button>
                </li>
            @endforeach
        </ul>

        @foreach ($categories as $category)
            @php
                $formKey = 'kategoria-'.$category->id;
                $bag = $errors->getBag($formKey);
                $mine = old('form') === $formKey;
                $old = fn (string $key, mixed $default) => $mine ? old($key, $default) : $default;
                $english = $category->translation('en');
                $autoTitle = Seo::title($category->name, __('catalog::shop.category_title_suffix', [], 'pl'));
            @endphp
            <form id="{{ $formKey }}" method="post" action="{{ route('admin.categories.update', $category) }}" novalidate class="{{ $card }}"
                  x-show="open === @js($formKey)" @if ($mistake !== $formKey) style="display: none" @endif data-unsaved-name="{{ $category->name }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="form" value="{{ $formKey }}">
                <button type="button" x-on:click="open = null" class="-mt-2 mb-1 inline-flex min-h-11 items-center gap-1.5 text-[13px] text-lead hover:text-ink">← Wszystkie półki</button>
                <div class="mb-4 flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="font-serif text-[23px]">{{ $category->name }}</h2>
                    <a href="{{ route('shop.category', $category) }}" target="_blank" rel="noopener" class="text-[13px]">/sklep/{{ $category->slug }} — produktów: {{ $category->products_count }}</a>
                </div>
                @if ($bag->any())
                    <p role="alert" data-error class="mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać.</p>
                @endif
                <div class="grid gap-3.5">
                    <x-shared::seo-field name="seo_title" :id="$formKey.'-title'" label="Tytuł w Google" :limit="60" :max="120" :bag="$formKey"
                                         :value="$old('seo_title', $category->seo_title)" :placeholder="$autoTitle" />
                    <x-shared::seo-field name="seo_description" :id="$formKey.'-description'" label="Opis w Google" :limit="155" :max="255" :rows="2" :bag="$formKey"
                                         :value="$old('seo_description', $category->seo_description)" placeholder="nazwa półki, co na niej stoi i czas wysyłki" />
                    <div class="min-w-0">
                        <label for="{{ $formKey }}-intro" class="mb-1.5 block text-[13.5px] text-graphite">Wstęp nad produktami</label>
                        <textarea id="{{ $formKey }}-intro" name="intro" rows="3" maxlength="600" placeholder="dwa, trzy zdania o tej półce"
                                  @class([$input, 'resize-y', 'border-error' => $bag->has('intro'), 'border-line' => ! $bag->has('intro')])>{{ $old('intro', $category->intro) }}</textarea>
                        @if ($bag->has('intro'))
                            <p class="mt-1.5 text-[13px] text-error">{{ $bag->first('intro') }}</p>
                        @else
                            <p class="mt-1.5 text-[12.5px] text-hint">Puste pole nie pokaże się na stronie.</p>
                        @endif
                    </div>

                    @if ($english)
                        <fieldset lang="en" class="grid gap-3.5 rounded-[4px] border border-sand-dark bg-linen px-4 pt-3 pb-4">
                            <legend lang="pl" class="mb-0 px-1 text-[11px] tracking-[0.14em] text-hint uppercase">Po angielsku — /en/shop/{{ $english->slug }}</legend>
                            <div lang="pl" class="grid gap-3.5">
                                <x-shared::seo-field name="en[seo_title]" error-key="en.seo_title" :id="$formKey.'-en-title'" label="Tytuł w Google po angielsku" :limit="60" :max="120" :bag="$formKey"
                                                     :value="$old('en.seo_title', $english->seo_title)" :placeholder="Seo::title($english->name, __('catalog::shop.category_title_suffix', [], 'en'))" />
                                <x-shared::seo-field name="en[seo_description]" error-key="en.seo_description" :id="$formKey.'-en-description'" label="Opis w Google po angielsku" :limit="155" :max="255" :rows="2" :bag="$formKey"
                                                     :value="$old('en.seo_description', $english->seo_description)" placeholder="what is on this shelf and when it ships" />
                                <div class="min-w-0">
                                    <label for="{{ $formKey }}-en-intro" class="mb-1.5 block text-[13.5px] text-graphite">Wstęp nad produktami po angielsku</label>
                                    <textarea id="{{ $formKey }}-en-intro" name="en[intro]" rows="3" maxlength="600" placeholder="two or three sentences about this shelf"
                                              @class([$input, 'resize-y', 'border-error' => $bag->has('en.intro'), 'border-line' => ! $bag->has('en.intro')])>{{ $old('en.intro', $english->intro) }}</textarea>
                                    @if ($bag->has('en.intro'))
                                        <p class="mt-1.5 text-[13px] text-error">{{ $bag->first('en.intro') }}</p>
                                    @endif
                                </div>
                            </div>
                        </fieldset>
                    @endif
                </div>
                <div class="mt-[18px] flex flex-wrap gap-2.5">
                    <button class="min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zapisz</button>
                    @if (! $loop->last)
                        <button name="next" value="{{ $categories[$loop->index + 1]->id }}" class="min-h-11 rounded-full border border-line-strong px-6 py-3 text-[13.5px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark">Zapisz i następna →</button>
                    @endif
                </div>
            </form>
        @endforeach
    </div>
    </x-admin::tab-panel>
  </x-admin::tabs>
</x-admin::layout>
