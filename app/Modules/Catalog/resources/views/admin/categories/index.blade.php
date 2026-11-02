@use('App\Modules\Catalog\Http\Requests\Admin\SaveShopShortcutsRequest')
@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\Shared\Support\Seo')
@use('App\Modules\Catalog\Actions\DeleteCategory')
@use('App\Modules\Catalog\Http\Requests\Admin\StoreCategoryRequest')
@use('App\Modules\Catalog\Models\Category')
{{--
    Kategorie (MA-318, MA-351): the tree of shelves under Glina and Szycie, arranged by dragging ⠿, a new shelf under it,
    one shelf open at a time with its name, its place in the tree, what Google shows and „Zapisz i następna”,
    and the captions under the shop's shortcut cards in a tab of their own.
--}}
@php
    $card = 'scroll-mt-24 rounded-[4px] border border-line bg-cream px-[18px] py-5 md:p-[22px]';
    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-4 py-[13px] text-[15px] leading-[1.6] text-ink placeholder:text-hint focus:border-ink';
    $keys = $categories->map(fn ($category) => 'kategoria-'.$category->id)->values()->all();
    $mistake = collect($keys)->first(fn ($key) => $errors->getBag($key)->any());
    $englishShop = Locales::isEnabled('en');
    // Where a new shelf can go: Glina, Szycie and their shelves, so the tree stays three levels deep.
    $parents = $tree->shopTop()->flatMap(fn ($top) => [$top, ...$tree->children($top)])->values();
    // Nothing on the shelf or under it: it waits outside the shop until a product stands on it.
    $filled = function ($category) use (&$filled, $tree) {
        return $category->products_count + $category->also_shown_products_count + $tree->children($category)->sum(fn ($child) => $filled($child));
    };
@endphp
<x-admin::layout title="Kategorie" lead="Półki w sklepie: Glina, Szycie i to, co w nich stoi. Kolejność zmienisz, przeciągając ⠿. W każdej półce — co Google pokaże i krótki wstęp nad produktami.">
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
        <div x-show="open === null" @if ($mistake) style="display: none" @endif class="grid gap-[22px]">
            {{-- The tree: a shelf moves by its handle, also to another branch or under another shelf (MA-351). Glina and Szycie stay. --}}
            <div x-data="categoryTree(@js(['arrangeUrl' => route('admin.categories.arrange'), 'token' => csrf_token(), 'maxDepth' => Category::MAX_DEPTH]))" class="grid gap-[22px]">
                @foreach ($tree->shopTop() as $top)
                    <section aria-labelledby="galaz-{{ $top->id }}" class="overflow-hidden rounded-[4px] border border-line bg-cream">
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 px-[18px] py-3.5">
                            @php
                                $google = filled($top->seo_title) && filled($top->seo_description);
                            @endphp
                            <div class="min-w-0 flex-[1_1_160px]">
                                <h2 id="galaz-{{ $top->id }}" class="font-serif text-[23px]">{{ $top->name }}</h2>
                                <span @class(['text-[12.5px]', 'text-success' => $google, 'text-label' => ! $google])>{{ $google ? 'Google ✓' : 'Google: złoży sama' }}</span>
                            </div>
                            <button type="button" x-on:click="addUnder({{ $top->id }})" class="min-h-11 rounded-full border border-line-strong px-4 text-[13px] text-ink hover:border-ink">+ Półka</button>
                            <button type="button" x-on:click="open = @js('kategoria-'.$top->id)" aria-controls="kategoria-{{ $top->id }}" class="min-h-11 px-1 text-[13px] text-brown hover:text-ink">Google i wstęp →</button>
                        </div>
                        <ol data-children-of="{{ $top->id }}" data-depth="2" class="category-list">
                            @foreach ($tree->children($top) as $child)
                                @include('catalog::admin.categories.tree-item', ['category' => $child, 'depth' => 2])
                            @endforeach
                        </ol>
                    </section>
                @endforeach

                <p class="text-[12.5px] text-hint">Złap półkę za ⠿ i upuść w nowym miejscu — także w innej półce, żeby była jej podkategorią. Najwyżej trzy poziomy, np. Glina › Biżuteria › Kolczyki. Strzałki w górę i w dół na ⠿ przesuwają ją w obrębie jej półki; do innej przeniesiesz ją w karcie, polem „Gdzie stoi”.</p>

                <div role="status" x-show="notice" x-cloak x-transition.opacity
                     class="fixed bottom-[calc(26px+env(safe-area-inset-bottom))] left-1/2 z-120 flex w-max max-w-[calc(100%-32px)] -translate-x-1/2 items-center gap-3 rounded-full bg-ink px-6 py-3.5 text-[14px] text-sand shadow-pill">
                    <span x-text="notice"></span>
                </div>
            </div>

            {{-- A new shelf: its name, its English name and where it stands; under the tree, so a phone opens on the shelves; „+ Półka” in the tree fills in the place and scrolls here. --}}
            @php
                $bag = $errors->getBag(StoreCategoryRequest::FORM);
            @endphp
            <form id="{{ StoreCategoryRequest::FORM }}" method="post" action="{{ route('admin.categories.store') }}" novalidate class="{{ $card }}">
                @csrf
                <h2 class="mb-1.5 font-serif text-[23px]">Dodaj półkę</h2>
                <p class="mb-4 text-[13.5px] text-muted">Pusta półka czeka poza sklepem — pokaże się, gdy postawisz na niej produkt.</p>
                @if ($bag->any())
                    <p role="alert" data-error class="mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby dodać półkę.</p>
                @endif
                <div @class(['grid gap-3.5', 'sm:grid-cols-2' => $englishShop])>
                    <x-shared::field name="name" id="nowa-polka-nazwa" label="Nazwa" :bag="StoreCategoryRequest::FORM" maxlength="60" placeholder="np. Kubki do espresso"
                                     :value="$bag->any() ? old('name') : null" />
                    @if ($englishShop)
                        <div class="min-w-0">
                            <label for="nowa-polka-en" class="mb-1.5 block text-[13.5px] text-graphite">Nazwa po angielsku <span class="text-hint">— nieobowiązkowe</span></label>
                            <input id="nowa-polka-en" name="en[name]" lang="en" maxlength="60" value="{{ $bag->any() ? old('en.name') : '' }}" placeholder="np. Espresso cups"
                                   aria-describedby="nowa-polka-en-note" @if ($bag->has('en.name')) aria-invalid="true" @endif class="field-control">
                            <p id="nowa-polka-en-note" @class(['mt-1.5 text-[12.5px]', 'text-error' => $bag->has('en.name'), 'text-hint' => ! $bag->has('en.name')])>{{ $bag->first('en.name') ?: 'Bez niej półka nie pokaże się w sklepie po angielsku.' }}</p>
                        </div>
                    @endif
                    <div class="min-w-0">
                        <label for="nowa-polka-gdzie" class="mb-1.5 block text-[13.5px] text-graphite">Gdzie stoi</label>
                        <select id="nowa-polka-gdzie" name="parent_id" @if ($bag->has('parent_id')) aria-invalid="true" @endif class="field-control">
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}" @selected((int) old('parent_id') === $parent->id)>{{ $tree->label($parent) }}</option>
                            @endforeach
                        </select>
                        @if ($bag->has('parent_id'))
                            <p class="mt-1.5 text-[13px] text-error">{{ $bag->first('parent_id') }}</p>
                        @endif
                    </div>
                </div>
                <button class="mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Dodaj półkę</button>
            </form>

            {{-- Outside the tree: vouchers have their own page, so they never move. --}}
            @php
                $outside = $tree->children()->reject(fn ($top) => $tree->shopTop()->contains('id', $top->id));
            @endphp
            @if ($outside->isNotEmpty())
                <section aria-labelledby="poza-drzewem" class="rounded-[4px] border border-line bg-cream px-[18px] py-3.5">
                    <h2 id="poza-drzewem" class="font-serif text-[20px]">Poza Gliną i Szyciem</h2>
                    <p class="mb-2 text-[12.5px] text-label">Mają własną stronę, więc nie stoją w drzewie.</p>
                    <ul class="grid gap-1">
                        @foreach ($outside as $category)
                            <li class="flex flex-wrap items-center justify-between gap-x-4">
                                <span class="text-[15px] text-ink">{{ $category->name }} <span class="text-[12.5px] text-label">· produktów: {{ $category->products_count }}</span></span>
                                <button type="button" x-on:click="open = @js('kategoria-'.$category->id)" aria-controls="kategoria-{{ $category->id }}" class="min-h-11 px-1 text-[13px] text-brown hover:text-ink">Google i wstęp →</button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </div>

        @foreach ($categories as $category)
            @php
                $formKey = 'kategoria-'.$category->id;
                $bag = $errors->getBag($formKey);
                $mine = old('form') === $formKey;
                $old = fn (string $key, mixed $default) => $mine ? old($key, $default) : $default;
                $english = $category->translation('en');
                $blocker = DeleteCategory::blocker($category, $tree);
                $autoTitle = Seo::title($category->name, __('catalog::shop.category_title_suffix', [], 'pl'));
            @endphp
            <form id="{{ $formKey }}" method="post" action="{{ route('admin.categories.update', $category) }}" novalidate class="{{ $card }}"
                  x-show="open === @js($formKey)" @if ($mistake !== $formKey) style="display: none" @endif data-unsaved-name="{{ $category->name }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="form" value="{{ $formKey }}">
                <button type="button" x-on:click="open = null" class="-mt-2 mb-1 inline-flex min-h-11 items-center gap-1.5 text-[13px] text-lead hover:text-ink">← Wszystkie półki</button>
                <div class="mb-4 flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="font-serif text-[23px]">{{ $tree->label($category) }}</h2>
                    <a href="{{ route('shop.category', $category) }}" target="_blank" rel="noopener" class="text-[13px]">/sklep/{{ $category->slug }} — produktów: {{ $category->products_count }}</a>
                </div>
                @if ($bag->any())
                    <p role="alert" data-error class="mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać.</p>
                @endif
                <div class="grid gap-3.5">
                    {{-- The name and the place in the tree (MA-351). The address stays as it was, so a link never breaks. --}}
                    <div @class(['grid gap-3.5', 'sm:grid-cols-2' => $englishShop])>
                        <x-shared::field name="name" :id="$formKey.'-name'" label="Nazwa" :bag="$formKey" maxlength="60" :value="$old('name', $category->name)"
                                         hint="Adres w sklepie zostaje ten sam." />
                        @if ($englishShop)
                            <div class="min-w-0">
                                <label for="{{ $formKey }}-en-name" class="mb-1.5 block text-[13.5px] text-graphite">Nazwa po angielsku</label>
                                <input id="{{ $formKey }}-en-name" name="en[name]" lang="en" maxlength="60" value="{{ $old('en.name', $english?->name) }}"
                                       aria-describedby="{{ $formKey }}-en-name-note" @if ($bag->has('en.name')) aria-invalid="true" @endif class="field-control">
                                <p id="{{ $formKey }}-en-name-note" @class(['mt-1.5 text-[12.5px]', 'text-error' => $bag->has('en.name'), 'text-hint' => ! $bag->has('en.name')])>{{ $bag->first('en.name') ?: ($english ? 'Puste pole zabierze półkę ze sklepu po angielsku.' : 'Bez niej półka nie pokaże się w sklepie po angielsku.') }}</p>
                            </div>
                        @endif
                    </div>
                    @if (! $category->isTop())
                        @php
                            $mine = $tree->subtreeIds($category);
                            $height = $tree->height($category);
                            $places = $parents->reject(fn ($parent) => in_array($parent->id, $mine, true) || $tree->depth($parent) + $height > Category::MAX_DEPTH);
                        @endphp
                        <div class="min-w-0">
                            <label for="{{ $formKey }}-parent" class="mb-1.5 block text-[13.5px] text-graphite">Gdzie stoi</label>
                            <select id="{{ $formKey }}-parent" name="parent_id" aria-describedby="{{ $formKey }}-parent-note" @if ($bag->has('parent_id')) aria-invalid="true" @endif class="field-control">
                                @foreach ($places as $parent)
                                    <option value="{{ $parent->id }}" @selected((int) $old('parent_id', $category->parent_id) === $parent->id)>{{ $tree->label($parent) }}</option>
                                @endforeach
                            </select>
                            <p id="{{ $formKey }}-parent-note" @class(['mt-1.5 text-[12.5px]', 'text-error' => $bag->has('parent_id'), 'text-hint' => ! $bag->has('parent_id')])>{{ $bag->first('parent_id') ?: 'Przeniesiona półka staje na końcu nowego miejsca, ze wszystkim, co w niej jest.' }}</p>
                        </div>
                    @endif
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
                <div class="mt-[18px] flex flex-wrap items-center gap-2.5">
                    <button class="min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zapisz</button>
                    @if (! $loop->last)
                        <button name="next" value="{{ $categories[$loop->index + 1]->id }}" class="min-h-11 rounded-full border border-line-strong px-6 py-3 text-[13.5px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark">Zapisz i następna →</button>
                    @endif
                    {{-- Only an empty shelf goes; a full one says what to move first (MA-351). --}}
                    @unless ($category->isTop())
                        @if ($blocker === null)
                            <button form="usun-{{ $formKey }}" class="ml-auto min-h-11 rounded-full px-4 text-[13.5px] text-error hover:bg-alert">Usuń półkę</button>
                        @else
                            <p class="ml-auto max-w-[36ch] text-[12.5px] text-hint">{{ $blocker }}</p>
                        @endif
                    @endunless
                </div>
            </form>
            @if (! $category->isTop() && $blocker === null)
                <form id="usun-{{ $formKey }}" method="post" action="{{ route('admin.categories.destroy', $category) }}" data-unsaved="off"
                      x-data x-on:submit="confirm(@js('Usunąć półkę „'.$category->name.'”? Nie da się tego cofnąć.')) || $event.preventDefault()">@csrf @method('DELETE')</form>
            @endif
        @endforeach
    </div>
    </x-admin::tab-panel>
  </x-admin::tabs>
</x-admin::layout>
