@php
    $bag = $errors->getBag('certyfikat');
    $texts = \App\Modules\Checkout\Support\CertificatePdf::TEXTS;
    $names = ['pl' => 'Po polsku', 'en' => 'Po angielsku — na certyfikacie zamówienia z angielskiej strony'];
    $fields = [
        'eyebrow' => 'Nagłówek nad cytatem',
        'quote' => 'Cytat — każda linijka w osobnej linii',
        'brand_note' => 'Dopisek pod logo',
    ];

    $input = 'w-full min-w-0 rounded-[4px] border bg-white px-3 py-2.5 text-[15px] text-ink placeholder:text-hint focus:border-ink';
    $label = 'mb-1 block text-[12.5px] text-graphite';
@endphp
<x-admin::layout title="Certyfikat unikatu" lead="Karta A6, którą wkładasz do paczki z każdą rzeczą zrobioną w dłoniach. Tu zmienisz teksty z jej przodu.">
    <form id="certyfikat" method="post" action="{{ route('admin.certificate.update') }}" novalidate class="max-w-[880px] scroll-mt-6 rounded-[4px] border border-line bg-cream p-[22px]">
        @csrf
        @method('PUT')
        {{-- Enter in a field saves the card instead of opening the preview. --}}
        <button tabindex="-1" aria-hidden="true" class="sr-only">Zapisz</button>

        <h2 class="mb-1.5 text-[11.5px] tracking-[0.16em] text-label uppercase">Teksty na przodzie karty</h2>
        <p class="mb-4 max-w-[62ch] text-[13.5px] leading-[1.6] text-muted">Puste pole drukuje tekst, który widzisz w nim na szaro. Nazwa pracy, numer, wymiary i opieka biorą się same z zamówienia i produktu.</p>
        @if ($bag->any())
            <p role="alert" class="mb-3.5 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">Popraw zaznaczone pola, żeby zapisać.</p>
        @endif

        <div class="grid gap-5">
            @foreach ($locales as $locale)
                <fieldset class="min-w-0 rounded-[4px] border border-sand-dark bg-linen p-3.5">
                    <legend class="px-1 text-[12.5px] text-graphite">{{ $names[$locale] ?? $locale }}</legend>
                    <div class="grid gap-3">
                        @foreach ($fields as $text => $fieldLabel)
                            @php
                                $key = \App\Modules\Checkout\Support\CertificatePdf::setting($text, $locale);
                                [$max, $lines] = $texts[$text];
                            @endphp
                            <div class="min-w-0">
                                <label for="certyfikat-{{ $key }}" class="{{ $label }}">{{ $fieldLabel }}</label>
                                @if ($lines > 1)
                                    <textarea id="certyfikat-{{ $key }}" name="{{ $key }}" rows="{{ $lines }}" maxlength="{{ $max }}" placeholder="{{ $defaults[$key] }}"
                                              @class([$input, 'resize-y leading-[1.6]', 'border-error' => $bag->has($key), 'border-line' => ! $bag->has($key)])>{{ old($key, $values[$key]) }}</textarea>
                                @else
                                    <input id="certyfikat-{{ $key }}" name="{{ $key }}" value="{{ old($key, $values[$key]) }}" maxlength="{{ $max }}" placeholder="{{ $defaults[$key] }}"
                                           @class([$input, 'border-error' => $bag->has($key), 'border-line' => ! $bag->has($key)])>
                                @endif
                                @if ($bag->has($key))
                                    <p class="mt-1.5 text-[13px] text-error">{{ $bag->first($key) }}</p>
                                @else
                                    <p class="mt-1.5 text-[12.5px] text-label">Do {{ $max }} znaków{{ $lines > 1 ? ', najwyżej '.$lines.' linijki' : '' }}.</p>
                                @endif
                            </div>
                        @endforeach
                    </div>
                    <button name="preview" value="{{ $locale }}" formaction="{{ route('admin.certificate.preview') }}" formtarget="_blank"
                            class="mt-3.5 min-h-11 rounded-full border border-line-strong px-5 py-3 text-[13.5px] text-ink hover:border-ink hover:bg-sand-dark">Podgląd PDF{{ count($locales) > 1 ? ' — '.($locale === 'pl' ? 'po polsku' : 'po angielsku') : '' }}</button>
                </fieldset>
            @endforeach
        </div>

        <p class="mt-3.5 text-[12.5px] leading-[1.6] text-label">Podgląd otwiera w nowej karcie przykładowy certyfikat z tym, co jest teraz w polach — jeszcze bez zapisywania. Zapisane teksty mają wszystkie certyfikaty drukowane od tej chwili, także do starszych zamówień.</p>
        <button class="mt-[18px] min-h-11 rounded-full bg-ink px-6 py-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zapisz teksty certyfikatu</button>
    </form>
</x-admin::layout>
