{{-- „Powiadomienia” (MA-345): this phone's switch and the list of devices of this account. --}}
@php($owner = auth()->user()->isOwner())
<x-admin::layout title="Powiadomienia" :lead="$owner
    ? 'Telefon dzwoni, gdy przyjdzie opłacone zamówienie, zapis na warsztat albo pomysł na zamówienie indywidualne.'
    : 'Telefon dzwoni, gdy przyjdzie nowe opłacone zamówienie do spakowania.'">
    <div class="flex flex-wrap gap-[26px]">
        <section class="min-w-0 flex-[1_1_340px] rounded-[4px] border border-line bg-cream px-[26px] py-7" aria-labelledby="this-phone">
            <h2 id="this-phone" class="mb-1.5 font-serif text-[23px]">Ten telefon</h2>

            @if ($publicKey === null)
                <p class="text-[13.5px] leading-[1.55] text-label">Powiadomienia czekają jeszcze na klucze na serwerze. Gdy Maciej je wpisze, włączysz je tutaj jednym przyciskiem.</p>
            @else
                <div x-data="panelPush(@js([
                    'key' => $publicKey,
                    'store' => route('admin.push.store'),
                    'forget' => route('admin.push.forget'),
                    'test' => route('admin.push.test'),
                    'csrf' => csrf_token(),
                ]))" class="grid gap-4">
                    <p class="text-[13.5px] leading-[1.55] text-label">Na zablokowanym ekranie widać tylko numer i kwotę — bez imienia i adresu klientki.</p>

                    <p x-show="state === 'working'" class="text-[13.5px] text-label">Sprawdzam ten telefon…</p>

                    <div x-show="state === 'home-screen'" x-cloak class="grid gap-2 text-[13.5px] leading-[1.55] text-graphite">
                        <p>Na iPhonie powiadomienia działają tylko z panelu dodanego do ekranu początkowego (iOS 16.4 lub nowszy):</p>
                        <ol class="ml-5 list-decimal">
                            <li>W Safari dotknij „Udostępnij”.</li>
                            <li>Wybierz „Do ekranu początkowego”.</li>
                            <li>Otwórz panel z nowej ikony i wróć tutaj.</li>
                        </ol>
                    </div>

                    <p x-show="state === 'unsupported'" x-cloak class="text-[13.5px] leading-[1.55] text-graphite">Ta przeglądarka nie obsługuje powiadomień. Otwórz panel w Chrome albo Safari, a na iPhonie — z ikony na ekranie początkowym.</p>

                    <p x-show="state === 'denied'" x-cloak class="text-[13.5px] leading-[1.55] text-graphite">Powiadomienia są zablokowane dla tej strony. Włącz je w ustawieniach telefonu (Ustawienia → Powiadomienia → MellowAura) i odśwież ten ekran.</p>

                    <div x-show="state === 'off'" x-cloak>
                        <button type="button" x-on:click="turnOn()" class="w-full rounded-full bg-ink p-4 text-[14px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Włącz powiadomienia na tym telefonie</button>
                    </div>

                    <div x-show="state === 'on'" x-cloak class="grid gap-2.5">
                        <p class="text-[14px] text-ink">Na tym telefonie powiadomienia są włączone.</p>
                        <div class="flex flex-wrap gap-2.5">
                            <button type="button" x-on:click="sendTest()" class="inline-flex min-h-11 items-center rounded-full bg-ink px-5 text-[13.5px] text-linen hover:bg-rose hover:text-ink">Wyślij próbne</button>
                            <button type="button" x-on:click="turnOff()" class="inline-flex min-h-11 items-center rounded-full border border-line-strong px-5 text-[13.5px] text-lead hover:border-ink hover:text-ink">Wyłącz na tym telefonie</button>
                        </div>
                    </div>

                    <p x-show="message" x-text="message" x-cloak role="status" class="text-[13.5px] text-graphite"></p>
                </div>
            @endif
        </section>

        <section class="grid min-w-0 flex-[1_1_340px] content-start gap-2.5" aria-labelledby="devices-heading">
            <h2 id="devices-heading" class="font-serif text-[23px]">Twoje urządzenia</h2>
            <div id="push-devices" class="grid gap-2.5">
                @forelse ($devices as $device)
                    <article class="flex flex-wrap items-center gap-x-5 gap-y-3 rounded-[4px] border border-line bg-cream px-[18px] py-4">
                        <div class="min-w-0 flex-[1_1_200px]">
                            <h3 class="text-[14.5px] text-ink">{{ $device->device }}</h3>
                            <div class="mt-0.5 text-[12.5px] text-label">
                                włączone {{ $device->created_at->locale('pl')->isoFormat('D MMMM YYYY') }}@if ($device->last_sent_at) · ostatnie powiadomienie {{ $device->last_sent_at->locale('pl')->isoFormat('D MMMM, HH:mm') }}@endif
                            </div>
                        </div>
                        <form method="post" action="{{ route('admin.push.destroy', $device) }}">
                            @csrf
                            @method('delete')
                            <button aria-label="Usuń: {{ $device->device }}" class="inline-flex min-h-11 items-center rounded-full border border-line-strong px-4 text-[12.5px] text-lead hover:border-error hover:text-error">Usuń</button>
                        </form>
                    </article>
                @empty
                    <p class="rounded-[4px] border border-line bg-cream px-[18px] py-4 text-[13.5px] text-label">Żaden telefon jeszcze nie dostaje powiadomień.</p>
                @endforelse
            </div>
        </section>
    </div>
</x-admin::layout>
