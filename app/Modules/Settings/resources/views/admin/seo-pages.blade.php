@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\Shared\Support\PageSeo')
@inject('pageSeo', 'App\Modules\Shared\Support\PageSeo')
<x-admin::layout title="Google" lead="Tytuł i opis, które Google pokazuje dla każdej strony. Puste pole — strona złoży je sama, jak dotąd. Podpowiedź w polu pokazuje, co złoży.">
    <form method="post" action="{{ route('admin.seo-pages.update') }}" novalidate class="grid max-w-[880px] gap-[22px]">
        @csrf
        @method('PUT')
        @if ($errors->any())
            <p role="alert" class="rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif

        @foreach (PageSeo::PAGES as $page => $label)
            @continue(! Locales::has($page, 'pl'))
            <section id="strona-{{ str_replace('.', '-', $page) }}" class="scroll-mt-6 rounded-[4px] border border-line bg-cream p-[22px]">
                <div class="mb-4 flex flex-wrap items-baseline justify-between gap-3">
                    <h2 class="font-serif text-[23px]">{{ $label }}</h2>
                    <a href="{{ route($page) }}" target="_blank" rel="noopener" class="text-[13px]">{{ parse_url(route($page), PHP_URL_PATH) ?: '/' }}</a>
                </div>
                <div class="grid gap-3.5">
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
            </section>
        @endforeach

        <div class="sticky bottom-4">
            <button class="min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen shadow-card-hover transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zapisz wszystkie strony</button>
        </div>
    </form>
</x-admin::layout>
