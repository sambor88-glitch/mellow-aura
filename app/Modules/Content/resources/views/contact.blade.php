@use('Illuminate\Support\Facades\Vite')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $lead = $settings->get('text_contact_lead');
    $phone = $settings->get('contact_phone');
    $instagram = ltrim((string) $settings->get('instagram_handle'), '@');
    $email = $settings->get('contact_email');
    $location = $settings->get('location_description');
    $topics = (array) $settings->get('contact_form_topics', []);
    // A link such as /kontakt?temat=Wypał moich prac picks the topic in advance.
    $chosenTopic = old('topic', in_array(request()->query('temat'), $topics, true) ? request()->query('temat') : null);
    $company = collect(['company_name', 'company_nip', 'company_regon', 'company_address'])->mapWithKeys(fn (string $key) => [$key => $settings->get($key)]);

    $description = match (true) {
        filled($phone) => 'Napisz na WhatsApp '.$phone.' albo przez formularz. Odpisuję zwykle tego samego dnia.',
        filled($instagram) => 'Napisz na Instagramie @'.$instagram.' albo przez formularz. Odpisuję zwykle tego samego dnia.',
        default => 'Napisz do mnie przez formularz. Odpisuję zwykle tego samego dnia.',
    };

    $channel = 'flex items-center justify-between gap-4 bg-cream px-6 py-[22px] text-ink hover:bg-sand-dark hover:text-ink';
    $channelLabel = 'mb-[5px] block text-[11px] tracking-[0.18em] text-gold uppercase';
@endphp

<x-shared::layout title="Kontakt — pracownia MellowAura, Kraków" :description="$description" :canonical="route('content.contact')">
    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <div class="flex flex-wrap gap-14">
            <div class="min-w-0 flex-[1_1_360px]">
                <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">kontakt</div>
                <h1 class="mb-6 font-serif text-[length:clamp(38px,5.2vw,66px)] leading-[1.04] font-light tracking-[-0.02em]">Napisz<br>do mnie</h1>
                @if ($lead)
                    <p class="mb-10 max-w-[46ch] text-[17px] leading-[1.7] text-lead">{{ $lead }}</p>
                @endif

                <div class="mb-8 grid gap-px overflow-hidden rounded-[4px] border border-divider bg-divider">
                    @if ($phone)
                        <a href="https://wa.me/{{ preg_replace('/\D+/', '', $phone) }}" target="_blank" rel="noopener" class="{{ $channel }}">
                            <span class="min-w-0"><span class="{{ $channelLabel }}">whatsapp &middot; najszybciej</span><span class="text-[17px]">{{ $phone }}</span></span>
                            <span aria-hidden="true" class="text-brown">→</span>
                        </a>
                    @endif
                    @if ($instagram)
                        <a href="https://www.instagram.com/{{ $instagram }}/" target="_blank" rel="noopener" class="{{ $channel }}">
                            <span class="min-w-0"><span class="{{ $channelLabel }}">instagram &middot; dm</span><span class="text-[17px]">{{ '@'.$instagram }}</span></span>
                            <span aria-hidden="true" class="text-brown">→</span>
                        </a>
                    @endif
                    @if ($email)
                        <a href="mailto:{{ $email }}" class="{{ $channel }}">
                            <span class="min-w-0"><span class="{{ $channelLabel }}">e-mail</span><span class="text-[17px] [overflow-wrap:anywhere]">{{ $email }}</span></span>
                            <span aria-hidden="true" class="text-brown">→</span>
                        </a>
                    @endif
                    @if ($location)
                        <div class="bg-cream px-6 py-[22px]">
                            <span class="{{ $channelLabel }}">pracownia</span>
                            <span class="text-[17px]">{{ $location }}</span>
                            <span class="mt-1 block text-[13.5px] text-label">Dokładny adres podaję przy zapisie na warsztat lub odbiorze zamówienia.</span>
                        </div>
                    @endif
                </div>

                <img src="{{ Vite::asset('zdjecia/talerz-niebieski-odcisk.webp') }}" alt="Talerz z odciskiem roślinnym" loading="lazy"
                     class="block aspect-[4/3] w-full max-w-[420px] rounded-[4px] bg-line-soft object-cover">
            </div>

            <div class="min-w-[290px] flex-[1_1_340px]">
                <div id="formularz" class="scroll-mt-56 rounded-[4px] border border-divider bg-cream px-[30px] py-8">
                    @if (session('contact_sent'))
                        <p role="status" class="mb-5 rounded-[4px] border border-line-strong bg-sand-dark p-4 text-[14px] leading-[1.6] text-graphite">Wiadomość poszła. Odpisuję zwykle tego samego dnia.</p>
                    @elseif (session('contact_failed'))
                        <p role="alert" class="mb-5 rounded-[4px] border border-alert-line bg-alert p-4 text-[14px] leading-[1.6] text-alert-text">
                            Coś się zacięło po mojej stronie i wiadomość nie wyszła. Twój tekst jest nadal w formularzu — spróbuj za chwilę{{ $phone ? ' albo napisz na WhatsApp '.$phone : '' }}.
                        </p>
                    @elseif (session('contact_throttled'))
                        <p role="alert" class="mb-5 rounded-[4px] border border-alert-line bg-alert p-4 text-[14px] leading-[1.6] text-alert-text">
                            Mam już od Ciebie kilka wiadomości i na wszystkie odpiszę. Kolejną wyślesz za godzinę{{ $phone ? ', a jeśli to pilne, napisz na WhatsApp '.$phone : '' }}.
                        </p>
                    @endif

                    <form method="post" action="{{ route('content.contact.send') }}" novalidate class="grid gap-[13px]">
                        @csrf
                        <x-shared::field name="name" id="contact-name" label="Imię" autocomplete="given-name" />
                        <x-shared::field name="email" id="contact-email" label="E-mail" type="email" autocomplete="email" hint="Na ten adres odpiszę" />
                        @if ($topics)
                            <div class="min-w-0">
                                <label for="contact-topic" class="mb-1.5 block text-[13.5px] text-graphite">W jakiej sprawie?</label>
                                <select id="contact-topic" name="topic"
                                        @error('topic') aria-invalid="true" aria-describedby="contact-topic-error" @enderror
                                        @class([
                                            'w-full min-w-0 rounded-[4px] border bg-white px-4 py-[15px] text-[15px] text-lead focus:border-ink',
                                            'border-error' => $errors->has('topic'),
                                            'border-line' => ! $errors->has('topic'),
                                        ])>
                                    <option value="">Wybierz, jeśli chcesz</option>
                                    @foreach ($topics as $topic)
                                        <option value="{{ $topic }}" @selected($chosenTopic === $topic)>{{ $topic }}</option>
                                    @endforeach
                                </select>
                                @error('topic')
                                    <p id="contact-topic-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif
                        <div class="min-w-0">
                            <label for="contact-message" class="mb-1.5 block text-[13.5px] text-graphite">Twoja wiadomość</label>
                            <textarea id="contact-message" name="message" rows="6" maxlength="3000"
                                      @error('message') aria-invalid="true" aria-describedby="contact-message-error" @enderror
                                      @class([
                                          'w-full min-w-0 resize-y rounded-[4px] border bg-white px-4 py-[14px] text-[15px] leading-[1.6] text-ink focus:border-ink',
                                          'border-error' => $errors->has('message'),
                                          'border-line' => ! $errors->has('message'),
                                      ])>{{ old('message') }}</textarea>
                            @error('message')
                                <p id="contact-message-error" class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                            @enderror
                        </div>
                        {{-- Hidden from people; a bot fills it in. --}}
                        <div aria-hidden="true" class="absolute -left-[9999px]">
                            <label for="contact-website">Strona internetowa</label>
                            <input id="contact-website" name="website" tabindex="-1" autocomplete="off">
                        </div>
                        <button class="rounded-full bg-ink p-4 text-[14.5px] text-linen transition duration-300 hover:bg-navy active:scale-[.97]">Wyślij wiadomość</button>
                        <p class="text-[12px] leading-[1.5] text-hint">Wiadomość trafi prosto do mnie mailem. Więcej o danych w <a href="{{ route('content.privacy') }}">polityce prywatności</a>.</p>
                    </form>
                </div>

                @if ($company->filter()->isNotEmpty())
                    <div class="mt-6 rounded-[4px] border border-divider px-[30px] py-6 text-[13.5px] leading-[1.7] text-muted">
                        <div class="mb-1.5 text-[11px] tracking-[0.18em] text-label uppercase">sprzedawca</div>
                        @if ($company['company_name'])
                            <div class="text-ink">{{ $company['company_name'] }}</div>
                        @endif
                        @if ($company['company_address'])
                            <div>{{ $company['company_address'] }}</div>
                        @endif
                        <div>{{ collect([$company['company_nip'] ? 'NIP '.$company['company_nip'] : null, $company['company_regon'] ? 'REGON '.$company['company_regon'] : null])->filter()->join(' · ') }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-shared::layout>
