@php
    $description = 'Voucher na lepienie z ręki, warsztat dla pary albo kwotowy. PDF z imieniem i dedykacją zaraz po opłaceniu, ważny '.$validity.', termin do wyboru.';
    $chip = 'rounded-full bg-sand-dark px-[13px] py-1.5 text-[12px] tracking-[0.08em] text-muted uppercase';
    $steps = [
        ['Wybierasz voucher', 'Wpisujesz imię obdarowanej osoby, swoje i dedykację. Przed zapłatą widzisz, jak będzie wyglądał.'],
        ['Dostajesz PDF', 'Przychodzi mailem zaraz po zaksięgowaniu płatności. Możesz go wydrukować albo przesłać dalej.'],
        ['Obdarowana osoba wybiera termin', collect([$howToUse ? Str::ucfirst(trim($howToUse)) : 'Pisze do mnie z numerem vouchera i razem ustalamy termin.', 'Voucher jest ważny '.$validity.' od zakupu.'])->join(' ')],
    ];
@endphp

<x-shared::layout title="Voucher na warsztaty ceramiczne w Krakowie | MellowAura" :description="$description" :canonical="route('vouchers.index')">
    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">prezent &middot; voucher</div>
        <h1 class="mb-6 max-w-[16ch] font-serif text-[length:clamp(38px,5.2vw,68px)] leading-[1.04] font-light tracking-[-0.02em] text-balance">Voucher na warsztaty ceramiczne w Krakowie</h1>
        @if ($lead)
            <p class="mb-6 max-w-[52ch] text-[17px] leading-[1.7] text-pretty text-lead">{{ $lead }}</p>
        @endif
        <ul class="mb-14 flex flex-wrap gap-2">
            <li class="{{ $chip }}">PDF zaraz po opłaceniu</li>
            <li class="{{ $chip }}">ważny {{ $validity }}</li>
            <li class="{{ $chip }}">imię i dedykacja na voucherze</li>
        </ul>

        @if ($vouchers->isNotEmpty())
            <h2 class="sr-only">Vouchery do wyboru</h2>
            <div class="mb-[72px] grid grid-cols-[repeat(auto-fill,minmax(min(100%,260px),1fr))] gap-x-[26px] gap-y-10">
                @foreach ($vouchers as $voucher)
                    <div class="min-w-0">
                        <x-catalog::product-card :product="$voucher" :delay="$loop->index * 0.06" :eager="$loop->index < 2" :with-variant-count="false" />
                        @if ($expectations->has($voucher->id))
                            <p class="text-[14px] leading-[1.6] text-muted">{{ $expectations->get($voucher->id) }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        @else
            <div class="mb-[72px] rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
                <h2 class="mb-2 font-serif text-[24px] font-normal">Vouchery wrócą niedługo</h2>
                <p class="mx-auto mb-5 max-w-[46ch] text-[14.5px] text-label">Warsztat na prezent nadal da się podarować — napisz, a przygotuję voucher.</p>
                <a href="{{ route('content.contact', ['temat' => 'Warsztaty i terminy']) }}" class="inline-block rounded-full bg-ink px-7 py-3.5 text-[14px] text-linen hover:bg-navy hover:text-linen">Napisz do mnie</a>
            </div>
        @endif

        <h2 class="mb-6 font-serif text-[length:clamp(28px,3.4vw,40px)] leading-[1.1] font-light">Jak to działa</h2>
        <ol class="mb-10 flex flex-wrap gap-px overflow-hidden rounded-[4px] border border-divider bg-divider">
            @foreach ($steps as [$title, $text])
                <li class="min-w-0 flex-[1_1_260px] bg-cream px-[26px] py-[30px]">
                    <div aria-hidden="true" class="mb-2 font-serif text-[20px] text-gold">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }}</div>
                    <h3 class="mb-1.5 text-[16px]">{{ $title }}</h3>
                    <p class="text-[14.5px] leading-[1.6] text-muted">{{ $text }}</p>
                </li>
            @endforeach
        </ol>

        <div class="flex flex-wrap items-center gap-x-8 gap-y-3 text-[14px]">
            @if (Route::has('workshops.index'))
                <a href="{{ route('workshops.index') }}" class="inline-flex min-h-11 items-center">Zobacz, jak wyglądają warsztaty →</a>
            @endif
            @if (Route::has('gifts.index'))
                <a href="{{ route('gifts.index') }}" class="inline-flex min-h-11 items-center">Szukasz innego prezentu? →</a>
            @endif
            @if ($phone)
                <span class="text-muted">Pytania o vouchery: <a href="https://wa.me/{{ preg_replace('/\D+/', '', $phone) }}" target="_blank" rel="noopener" class="whitespace-nowrap">WhatsApp {{ $phone }}</a></span>
            @endif
        </div>
    </div>
</x-shared::layout>
