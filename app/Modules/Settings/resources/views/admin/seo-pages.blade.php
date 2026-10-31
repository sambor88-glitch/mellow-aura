@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\Shared\Support\PageSeo')
@inject('pageSeo', 'App\Modules\Shared\Support\PageSeo')
<x-admin::layout title="Google" lead="Tytuł i opis, które Google pokazuje dla każdej strony. Puste pole — strona złoży je sama, jak dotąd. Podpowiedź w polu pokazuje, co złoży.">
    <form method="post" action="{{ route('admin.seo-pages.update') }}" novalidate class="grid max-w-[880px] gap-2.5">
        @csrf
        @method('PUT')
        @if ($errors->any())
            <p role="alert" data-error class="rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif

        @foreach (PageSeo::PAGES as $page => $label)
            @continue(! Locales::has($page, 'pl'))
            @php
                $mine = collect(['title', 'description'])->filter(fn ($part) => filled($saved[$page]['pl'][$part] ?? null))->count();
                $wrong = collect($errors->keys())->contains(fn ($key) => str_starts_with($key, 'pages.'.PageSeo::fieldKey($page).'.'));
            @endphp
            {{-- Each page folds to its name and whether its words are Kasia's own (MA-325); one with a mistake stays open. --}}
            <details id="strona-{{ str_replace('.', '-', $page) }}" class="group scroll-mt-24 rounded-[4px] border border-line bg-cream" @if ($wrong) open @endif>
                <summary class="flex min-h-14 cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 px-[18px] py-3 md:px-[22px] [&::-webkit-details-marker]:hidden">
                    <span class="min-w-0 flex-[1_1_180px]">
                        <span class="block text-[15px] text-ink">{{ $label }}</span>
                        <span class="block text-[12.5px] text-label">{{ parse_url(route($page), PHP_URL_PATH) ?: '/' }}</span>
                    </span>
                    <span @class(['rounded-full px-2.5 py-1 text-[12px]', 'bg-success-soft text-success' => $mine === 2, 'bg-linen text-label' => $mine < 2])>{{ match ($mine) { 2 => 'Twój tytuł i opis', 1 => 'częściowo Twoje', default => 'strona złoży sama' } }}</span>
                    <span aria-hidden="true" class="text-[15px] text-label transition-transform group-open:rotate-180">⌄</span>
                </summary>
                <div class="grid gap-3.5 border-t border-line px-[18px] pt-4 pb-5 md:px-[22px]">
                    <a href="{{ route($page) }}" target="_blank" rel="noopener" class="text-[13px]">Zobacz stronę →</a>
                    @foreach (['pl' => '', 'en' => ' po angielsku'] as $locale => $suffix)
                        @continue(! Locales::has($page, $locale))
                        @php
                            $key = PageSeo::fieldKey($page);
                            $field = "pages.$key.$locale";
                            $composed = $pageSeo->composed($page, $locale);
                            $fallback = 'strona złoży sama — podpowiedź pokaże się po pierwszym wejściu na stronę';
                        @endphp
                        <x-shared::seo-field :name="'pages['.$key.']['.$locale.'][title]'" :error-key="$field.'.title'" :id="'seo-'.$key.'-'.$locale.'-title'"
                                             :label="'Tytuł w Google'.$suffix" :limit="60" :max="120"
                                             :value="old($field.'.title', $saved[$page][$locale]['title'] ?? null)" :placeholder="$composed['title'] ?? $fallback" />
                        <x-shared::seo-field :name="'pages['.$key.']['.$locale.'][description]'" :error-key="$field.'.description'" :id="'seo-'.$key.'-'.$locale.'-description'"
                                             :label="'Opis w Google'.$suffix" :limit="155" :max="255" :rows="2"
                                             :value="old($field.'.description', $saved[$page][$locale]['description'] ?? null)" :placeholder="$composed['description'] ?? $fallback" />
                    @endforeach
                </div>
            </details>
        @endforeach

        <div class="sticky bottom-[calc(16px+env(safe-area-inset-bottom))] mt-3">
            <button class="min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen shadow-card-hover transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zapisz wszystkie strony</button>
        </div>
    </form>
</x-admin::layout>
