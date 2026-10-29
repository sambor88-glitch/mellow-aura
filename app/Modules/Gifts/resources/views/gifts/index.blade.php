@inject('settings', 'App\Modules\Settings\Settings')
@php
    $lead = $settings->get('text_gifts_lead');
    $voucherNote = $settings->get('text_gifts_voucher_note');
    $count = $ideas->count();
    $countLabel = match (true) {
        $count === 0 => 'Nic nie pasuje do tych warunków — poluzuj budżet albo zmień okazję',
        $count === 1 => 'Jeden pomysł',
        in_array($count % 10, [2, 3, 4], true) && ! in_array($count % 100, [12, 13, 14], true) => $count.' pomysły',
        default => $count.' pomysłów',
    };
@endphp

<x-shared::layout
    title="Szukam prezentu — ceramika handmade na każdą okazję"
    description="Prezent z ceramiki i jedwabiu: filtruj po okazji i budżecie. Pakowanie na prezent, kartka z życzeniami, wysyłka prosto do obdarowanej osoby."
    :canonical="route('gifts.index')"
>
    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">szukam prezentu</div>
        <h1 class="mb-[18px] font-serif text-[length:clamp(38px,5.2vw,68px)] leading-[1.04] font-light tracking-[-0.02em]">Powiedz, dla kogo<br>i za ile</h1>
        @if ($lead)
            <p class="mb-10 max-w-[54ch] text-[17px] leading-[1.7] text-lead">{{ $lead }}</p>
        @endif

        <div class="mb-[38px] rounded-[20px] border border-divider bg-cream px-6 py-[26px]">
            @foreach ($filters as $legend => $chips)
                @continue($chips->count() < 2)
                <nav aria-label="{{ $legend }}" @class(['mb-[22px]' => ! $loop->last])>
                    <div class="mb-3 text-[11.5px] tracking-[0.16em] text-label uppercase">{{ $legend }}</div>
                    {{-- On a phone each filter is one row swiped sideways, like the categories in the shop. --}}
                    <div x-init="const chip = $el.querySelector('[aria-current=true]'); if (chip && chip.offsetLeft + chip.offsetWidth > $el.clientWidth) $el.scrollLeft = chip.offsetLeft - 24"
                         class="relative -mx-6 flex snap-x snap-mandatory scroll-px-6 gap-[9px] overflow-x-auto px-6 pb-1 [scrollbar-width:none] md:mx-0 md:flex-wrap md:overflow-visible md:px-0 md:pb-0 [&::-webkit-scrollbar]:hidden">
                        @foreach ($chips as $chip)
                            <a href="{{ $chip['url'] }}" @if ($chip['active']) aria-current="true" @endif @class([
                                'shrink-0 snap-start rounded-full border px-[17px] py-[9px] text-[13px] whitespace-nowrap',
                                'border-ink bg-ink text-linen hover:text-linen' => $chip['active'],
                                'border-line-strong text-lead hover:border-ink hover:text-ink' => ! $chip['active'],
                            ])>{{ $chip['label'] }}</a>
                        @endforeach
                    </div>
                </nav>
            @endforeach
        </div>

        <div class="mb-6 text-[13.5px] text-label" role="status">{{ $countLabel }}</div>

        @if ($count === 0)
            <div class="rounded-[20px] bg-sand-dark px-9 py-11 text-center">
                <h2 class="mb-2 font-serif text-[26px] font-normal">Nic nie pasuje do tych warunków</h2>
                <p class="mb-[22px] text-[15.5px] text-lead">Poluzuj budżet albo napisz do mnie — zrobię coś na zamówienie w Twojej kwocie.</p>
                @if (Route::has('custom-orders.index'))
                    <a href="{{ route('custom-orders.index') }}" class="inline-block rounded-full bg-ink px-7 py-3.5 text-[14px] text-linen hover:bg-rose hover:text-ink">Napisz, czego szukasz</a>
                @else
                    <a href="{{ $resetUrl }}" class="inline-block rounded-full bg-ink px-7 py-3.5 text-[14px] text-linen hover:bg-rose hover:text-ink">Pokaż wszystkie pomysły</a>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-2 gap-x-3.5 gap-y-8 sm:grid-cols-[repeat(auto-fill,minmax(240px,1fr))] sm:gap-x-[26px] sm:gap-y-[30px]">
            @foreach ($ideas as $product)
                <x-catalog::product-card :product="$product" :delay="min($loop->index, 11) * 0.06" :eager="$loop->index < 2" />
            @endforeach
        </div>

        @if ($voucherCategory)
            <div class="focus-on-dark mt-[60px] flex flex-wrap items-center justify-between gap-[30px] rounded-[20px] bg-ink px-9 py-10 text-on-dark">
                <div class="min-w-0 flex-[1_1_320px]">
                    <h2 class="mb-2 font-serif text-[length:clamp(24px,3vw,32px)] leading-[1.2] font-normal text-cream">Nie wiesz, co wybrać?</h2>
                    <p class="text-[15.5px] text-on-dark-muted">{{ collect([$voucherNote, 'Ważny '.$voucherValidity.'.'])->filter()->join(' ') }}</p>
                </div>
                <a href="{{ route('vouchers.index') }}" class="flex-none rounded-full bg-rose px-[30px] py-[15px] text-[14.5px] text-ink hover:bg-sand hover:text-ink">Zobacz vouchery</a>
            </div>
        @endif

        <x-shared::service-links :services="['mug', 'imprint', 'scarf']" :heading="__('shared::services.gifts_heading')" class="mt-[60px]" />
    </div>
</x-shared::layout>
