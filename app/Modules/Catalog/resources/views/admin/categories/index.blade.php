@use('App\Modules\Shared\Support\Seo')
@php
    $card = 'scroll-mt-6 rounded-[4px] border border-line bg-cream p-[22px]';
    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-4 py-[13px] text-[15px] leading-[1.6] text-ink placeholder:text-hint focus:border-ink';
@endphp
<x-admin::layout title="Kategorie" lead="Co Google pokazuje dla każdej półki w sklepie i krótki wstęp nad jej produktami. Puste pole — strona złoży tytuł i opis sama.">
    <div class="grid max-w-[880px] gap-[22px]">
        @foreach ($categories as $category)
            @php
                $formKey = 'kategoria-'.$category->id;
                $bag = $errors->getBag($formKey);
                $mine = old('form') === $formKey;
                $old = fn (string $key, mixed $default) => $mine ? old($key, $default) : $default;
                $english = $category->translation('en');
                $autoTitle = Seo::title($category->name, __('catalog::shop.category_title_suffix', [], 'pl'));
            @endphp
            <form id="{{ $formKey }}" method="post" action="{{ route('admin.categories.update', $category) }}" novalidate class="{{ $card }}">
                @csrf
                @method('PUT')
                <input type="hidden" name="form" value="{{ $formKey }}">
                <div class="mb-4 flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="font-serif text-[23px]">{{ $category->name }}</h2>
                    <a href="{{ route('shop.category', $category) }}" target="_blank" rel="noopener" class="text-[13px]">/sklep/{{ $category->slug }} — produktów: {{ $category->products_count }}</a>
                </div>
                @if ($bag->any())
                    <p role="alert" class="mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać.</p>
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
                <button class="mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zapisz</button>
            </form>
        @endforeach
    </div>
</x-admin::layout>
