@use('App\Modules\Shared\Support\Seo')
@inject('pagePhotos', 'App\Modules\Content\Support\PagePhotos')
@php
    // The topic name matches the contact form's list from the panel; if it is renamed, the form simply opens without it.
    $contactUrl = route('content.contact', ['temat' => 'Zamówienie indywidualne']);
    $whatsApp = $phone ? 'https://wa.me/'.preg_replace('/\D+/', '', $phone) : null;
    // Photos by place, from the panel's „Zdjęcia stron” (Content); a place without a photo is left out.
    $photos = collect([
        ['custom-orders-1', 'Filiżanka z talerzykiem z malowanym wzorem'],
        ['custom-orders-2', 'Kubek z wbijanym napisem'],
        ['custom-orders-3', 'Różowe podstawki pod biżuterię w kształcie muszli'],
    ])->map(fn (array $row) => [$pagePhotos->get($row[0]), $row[1]])->filter(fn (array $row) => $row[0]->exists());
    $checklist = [
        'Co to ma być i ile sztuk',
        'Na kiedy tego potrzebujesz',
        'Budżet, jaki masz w głowie',
        $whatsApp ? 'Zdjęcia inspiracji — najłatwiej wysłać je na WhatsAppie' : 'Zdjęcia inspiracji, jeśli je masz',
    ];
@endphp

<x-shared::layout title="Zamówienia indywidualne — ceramika na zamówienie"
                  :description="Seo::description($lead ?? 'Ceramika na zamówienie z krakowskiej pracowni MellowAura.')"
                  :canonical="route('custom-orders.index')">
    <x-slot:head>{!! $structuredData !!}</x-slot:head>
    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <div class="flex flex-wrap gap-14">
            <div class="min-w-0 flex-[1_1_420px]">
                <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">zamówienia indywidualne</div>
                <h1 class="mb-[22px] font-serif text-[length:clamp(38px,5.2vw,68px)] leading-[1.04] font-light tracking-[-0.02em]">Zaprojektujmy<br>to razem</h1>
                @if ($lead)
                    <p class="mb-10 max-w-[52ch] text-[17px] leading-[1.7] text-pretty text-lead">{{ $lead }}</p>
                @endif

                @if ($steps->isNotEmpty())
                    <h2 class="sr-only">Jak powstaje zamówienie</h2>
                    <ol class="mb-11 grid gap-px overflow-hidden rounded-[20px] border border-divider bg-divider">
                        @foreach ($steps as $step)
                            <li class="flex items-baseline gap-5 bg-cream px-[26px] py-6">
                                <span aria-hidden="true" class="w-[26px] flex-none font-serif text-[22px] text-gold">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</span>
                                <div class="min-w-0">
                                    <h3 class="mb-1 text-[16px]">{{ $step['title'] }}</h3>
                                    @if (filled($step['text'] ?? null))
                                        <p class="text-[14.5px] leading-[1.6] text-muted">{{ $step['text'] }}</p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif

                {{-- Two to a row on a phone; an odd last photo takes the whole row instead of standing alone. --}}
                <div class="grid grid-cols-2 gap-3.5 sm:grid-cols-[repeat(auto-fit,minmax(160px,1fr))] max-sm:[&>*:last-child:nth-child(odd)]:col-span-2 max-sm:[&>*:last-child:nth-child(odd)]:aspect-[2/1]">
                    @foreach ($photos as [$photo, $alt])
                        <img src="{{ $photo->url }}" {{ $photo->srcsetAttributes('(min-width: 640px) 25vw, 100vw') }} alt="{{ $photo->alt($alt) }}" loading="lazy" width="600" height="600" style="{{ $photo->style() }}" class="block aspect-square w-full rounded-[20px] bg-line-soft object-cover">
                    @endforeach
                </div>
            </div>

            <div class="min-w-0 flex-[1_1_340px]">
                <div id="formularz" class="scroll-mt-6 rounded-[20px] border border-divider bg-cream px-[30px] py-8">
                    <h2 class="mb-1.5 font-serif text-[25px] font-normal">Opowiedz o pomyśle</h2>
                    @if ($sent)
                        <div role="status" class="mt-4 mb-6 rounded-[20px] border border-line-strong bg-sand-dark p-4 text-[14px] leading-[1.6] text-graphite">
                            <p>Mam Twój pomysł. Potwierdzenie poszło na {{ $sent->email }} — jeśli go nie widzisz, zajrzyj do spamu. Odpiszę ze szkicem i wyceną.</p>
                        </div>
                    @elseif (session('brief_throttled'))
                        <p role="alert" class="mt-4 mb-6 rounded-[20px] border border-alert-line bg-alert p-4 text-[14px] leading-[1.6] text-alert-text">
                            Mam już od Ciebie kilka wiadomości i na wszystkie odpiszę. Kolejną wyślesz za godzinę{{ $whatsApp ? ', a jeśli to pilne, napisz na WhatsAppie '.$phone : '' }}.
                        </p>
                    @else
                        <p class="mb-6 text-[14px] text-label">Kilka zdań wystarczy — resztę dopytam.</p>
                    @endif

                    @if ($withForm)
                        @php
                            $box = 'field-control';
                            $photoErrors = collect($errors->get('photos'))->merge(collect($errors->get('photos.*'))->flatten())->unique();
                            $kind = old('kind', $chosenKind?->value);
                        @endphp
                        <form method="post" action="{{ route('custom-orders.store') }}" enctype="multipart/form-data" novalidate class="grid gap-[13px]"
                              x-data="briefPhotos(@js($photoLimits))" x-on:submit="submit($event)">
                            @csrf
                            <x-shared::field name="name" id="brief-name" label="Imię" autocomplete="given-name" maxlength="120" />
                            <x-shared::field name="email" id="brief-email" label="E-mail" type="email" autocomplete="email" hint="Na ten adres odpiszę ze szkicem i wyceną" />
                            <x-shared::field name="phone" id="brief-phone" label="Telefon" type="tel" autocomplete="tel" maxlength="40" hint="Nieobowiązkowe — gdyby szybciej było coś ustalić głosem" />

                            <div class="min-w-0">
                                <label for="brief-kind" class="mb-1.5 block text-[13.5px] text-graphite">Co to ma być?</label>
                                <select id="brief-kind" name="kind"
                                        @error('kind') aria-invalid="true" aria-describedby="brief-kind-error" @enderror
                                        class="{{ $box }} text-lead">
                                    <option value="">Wybierz</option>
                                    @foreach ($kinds as $option)
                                        <option value="{{ $option->value }}" @selected($kind === $option->value)>{{ $option->label() }}</option>
                                    @endforeach
                                </select>
                                @error('kind')
                                    <p id="brief-kind-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="min-w-0">
                                <div class="grid grid-cols-2 gap-[13px]">
                                    <x-shared::field name="quantity" id="brief-quantity" label="Ile sztuk?" maxlength="60" placeholder="np. 6" describedby="brief-quantity-when-note" />
                                    <x-shared::field name="needed_by" id="brief-needed-by" label="Na kiedy?" maxlength="60" placeholder="np. 14 lutego" describedby="brief-quantity-when-note" />
                                </div>
                                <p id="brief-quantity-when-note" class="mt-1.5 text-[12.5px] text-hint">Nieobowiązkowe — jeśli już wiesz</p>
                            </div>
                            <x-shared::field name="budget" id="brief-budget" label="Budżet, jaki masz w głowie" maxlength="60" hint="Nieobowiązkowe — pomoże mi dobrać wykonanie" />

                            <div class="min-w-0">
                                <label for="brief-description" class="mb-1.5 block text-[13.5px] text-graphite">Twój pomysł</label>
                                <textarea id="brief-description" name="description" rows="5" maxlength="3000" placeholder="Kolor, kształt, dla kogo, skąd inspiracja"
                                          @error('description') aria-invalid="true" aria-describedby="brief-description-error" @enderror
                                          class="field-control resize-y leading-[1.6]">{{ old('description') }}</textarea>
                                @error('description')
                                    <p id="brief-description-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="min-w-0">
                                <label class="relative block cursor-pointer rounded-[20px] border border-dashed border-line-strong bg-linen p-[22px] text-center transition duration-300 hover:border-ink hover:bg-sand-dark active:scale-[.98] has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                    <span class="block text-[14px] text-lead" x-text="previews.length ? 'Dodaj kolejne zdjęcia' : 'Wybierz zdjęcia z telefonu albo komputera'">Wybierz zdjęcia z telefonu albo komputera</span>
                                    <span id="brief-photos-note" class="mt-1 block text-[12.5px] text-hint">Inspiracje, szkic, apaszka albo roślina — do {{ $photoLimits['maxFiles'] }} zdjęć JPG, PNG lub WebP, każde do {{ intdiv($photoLimits['maxFileBytes'], 1048576) }} MB</span>
                                    <input type="file" name="photos[]" x-ref="photos" accept="image/jpeg,image/png,image/webp" multiple x-on:change="add($el)" class="sr-only"
                                           @if ($photoErrors->isNotEmpty()) aria-invalid="true" aria-describedby="brief-photos-note brief-photos-error" @else aria-describedby="brief-photos-note" @endif>
                                </label>
                                <input type="hidden" name="photos_chosen" x-bind:value="previews.length">
                                <div x-show="previews.length" x-cloak class="mt-2.5 flex flex-wrap gap-2">
                                    <template x-for="(url, index) in previews" x-bind:key="url">
                                        <div class="relative h-[70px] w-[58px] rounded-[14px] border border-line bg-line-soft">
                                            {{-- The photo follows the frame's corner; no overflow-hidden, it would clip the finger-sized × above. --}}
                                            <img x-bind:src="url" x-bind:alt="'Zdjęcie ' + (index + 1)" width="58" height="70" class="size-full rounded-[13px] object-cover">
                                            <button type="button" x-on:click="remove(index)" x-bind:aria-label="'Nie wysyłaj zdjęcia ' + (index + 1)"
                                                    class="absolute top-[3px] right-[3px] grid size-5 place-items-center rounded-full bg-ink/75 text-[11px] leading-none text-linen transition-colors duration-300 after:absolute after:-inset-3 hover:bg-error">×</button>
                                        </div>
                                    </template>
                                </div>
                                <p role="alert" x-text="error" class="mt-1.5 text-[13px] text-error empty:hidden"></p>
                                @if ($photoErrors->isNotEmpty() || ($errors->any() && (int) old('photos_chosen') > 0))
                                    <div id="brief-photos-error">
                                        @foreach ($photoErrors as $message)
                                            <p class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                        @endforeach
                                        @if ((int) old('photos_chosen') > 0)
                                            <p class="mt-1.5 text-[13px] text-error">Zdjęcia wybierz jeszcze raz — po błędzie przeglądarka ich nie pamięta.</p>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Hidden from people; a bot fills it in. --}}
                            <div aria-hidden="true" class="absolute -left-[9999px]">
                                <label for="brief-website">Strona internetowa</label>
                                <input id="brief-website" name="website" tabindex="-1" autocomplete="off">
                            </div>
                            <x-shared::button x-text="preparing ? 'Przygotowuję zdjęcia…' : (sending ? 'Wysyłam…' : 'Wyślij pomysł')">Wyślij pomysł</x-shared::button>
                            <p class="text-[12px] leading-[1.5] text-hint">Opis i zdjęcia trafią tylko do mnie, a Ty dostaniesz kopię mailem. Więcej o danych w <a href="{{ route('content.privacy') }}">polityce prywatności</a>.</p>
                        </form>

                        @if ($whatsApp)
                            <p class="mt-5 border-t border-sand-dark pt-5 text-[13.5px] leading-[1.6] text-muted">Wolisz pisać na WhatsAppie? <a href="{{ $whatsApp }}" target="_blank" rel="noopener" class="whitespace-nowrap">{{ $phone }}</a></p>
                        @endif
                    @else
                        <ul class="mb-7 grid gap-2.5">
                            @foreach ($checklist as $item)
                                <li class="flex gap-2.5 text-[14.5px] text-lead"><span aria-hidden="true" class="text-gold">✦</span><span>{{ $item }}</span></li>
                            @endforeach
                        </ul>
                        <div class="grid gap-3">
                            @if ($whatsApp)
                                <x-shared::button href="{{ $whatsApp }}" target="_blank" rel="noopener">Napisz na WhatsAppie</x-shared::button>
                            @endif
                            <a href="{{ $contactUrl }}"
                               @class([
                                   'rounded-full p-[15px] text-center text-[14.5px] transition duration-300 active:scale-[.98]',
                                   'border border-line-strong text-ink hover:border-ink hover:bg-sand-dark hover:text-ink' => $whatsApp,
                                   'bg-ink text-linen hover:bg-rose hover:text-ink' => ! $whatsApp,
                               ])>Napisz przez formularz</a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-shared::layout>
