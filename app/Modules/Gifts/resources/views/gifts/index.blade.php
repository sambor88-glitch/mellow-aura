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

        <div class="mb-[38px] rounded-[4px] border border-divider bg-cream px-6 py-[26px]">
            @foreach ($filters as $legend => $chips)
                @continue($chips->count() < 2)
                <nav aria-label="{{ $legend }}" @class(['mb-[22px]' => ! $loop->last])>
                    <div class="mb-3 text-[11.5px] tracking-[0.16em] text-label uppercase">{{ $legend }}</div>
                    <div class="flex flex-wrap gap-[9px]">
                        @foreach ($chips as $chip)
                            <a href="{{ $chip['url'] }}" @if ($chip['active']) aria-current="true" @endif @class([
                                'rounded-full border px-[17px] py-[9px] text-[13px]',
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
            <div class="rounded-[4px] bg-sand-dark px-9 py-11 text-center">
                <h2 class="mb-2 font-serif text-[26px] font-normal">Nic nie pasuje do tych warunków</h2>
                <p class="mb-[22px] text-[15.5px] text-lead">Poluzuj budżet albo napisz do mnie — zrobię coś na zamówienie w Twojej kwocie.</p>
                @if (Route::has('custom-orders.index'))
                    <a href="{{ route('custom-orders.index') }}" class="inline-block rounded-full bg-ink px-7 py-3.5 text-[14px] text-linen hover:bg-navy hover:text-linen">Napisz, czego szukasz</a>
                @else
                    <a href="{{ $resetUrl }}" class="inline-block rounded-full bg-ink px-7 py-3.5 text-[14px] text-linen hover:bg-navy hover:text-linen">Pokaż wszystkie pomysły</a>
                @endif
            </div>
        @endif

        <div class="grid grid-cols-[repeat(auto-fill,minmax(240px,1fr))] gap-x-[26px] gap-y-[30px]">
            @foreach ($ideas as $product)
                @php
                    $image = $product->getFirstMedia('images');
                    $variant = $addable[$product->id];
                @endphp
                <div class="animate-ma-up" style="animation-delay: {{ min($loop->index, 11) * 0.06 }}s">
                    <a href="{{ route('product.show', $product) }}" class="group block text-ink hover:text-ink">
                        <div class="relative mb-3.5 overflow-hidden rounded-[6px] bg-line-soft transition-[box-shadow,transform] duration-500 ease-clay group-hover:-translate-y-1 group-hover:shadow-card-hover">
                            @if ($image)
                                <img src="{{ $image->getAvailableUrl(['card']) }}" alt="{{ $image->getCustomProperty('alt') ?: $product->name }}" @if ($loop->index >= 4) loading="lazy" @endif class="block aspect-[4/5] w-full object-cover">
                            @else
                                <div class="aspect-[4/5] w-full"></div>
                            @endif
                        </div>
                        <div class="mb-[5px] text-[10px] tracking-[0.22em] text-hint uppercase">{{ $product->category->name }}</div>
                        <div class="mb-1.5 font-serif text-[20px] leading-[1.25]">{{ $product->name }}</div>
                    </a>
                    <div class="mb-3 text-[14px] text-lead"><x-catalog::price-label :product="$product" /></div>
                    @if ($variant)
                        <form method="post" action="{{ route('cart.store') }}" x-data x-on:submit.prevent="$store.cart.send($el)">
                            @csrf
                            <input type="hidden" name="variant_id" value="{{ $variant->id }}">
                            <button class="min-h-11 rounded-full border border-line-strong px-[18px] py-[9px] text-[13px] text-ink transition duration-300 hover:border-ink hover:bg-ink hover:text-linen active:scale-[.97]">
                                Do koszyka<span class="sr-only">: {{ $product->name }}{{ $product->variants->count() > 1 ? ', '.$variant->label : '' }}</span>
                            </button>
                        </form>
                    @else
                        <a href="{{ route('product.show', $product) }}" class="inline-flex min-h-11 items-center rounded-full border border-line-strong px-[18px] py-[9px] text-[13px] text-ink transition duration-300 hover:border-ink hover:bg-ink hover:text-linen active:scale-[.97]">
                            Wybierz napis<span class="sr-only">: {{ $product->name }}</span>
                        </a>
                    @endif
                </div>
            @endforeach
        </div>

        @if ($voucherCategory)
            <div class="focus-on-dark mt-[60px] flex flex-wrap items-center justify-between gap-[30px] rounded-[4px] bg-ink px-9 py-10 text-on-dark">
                <div class="min-w-0 flex-[1_1_320px]">
                    <h2 class="mb-2 font-serif text-[length:clamp(24px,3vw,32px)] leading-[1.2] font-normal text-cream">Nie wiesz, co wybrać?</h2>
                    <p class="text-[15.5px] text-on-dark-muted">{{ collect([$voucherNote, 'Ważny '.$voucherValidity.'.'])->filter()->join(' ') }}</p>
                </div>
                <a href="{{ route('shop.category', $voucherCategory) }}" class="flex-none rounded-full bg-rose px-[30px] py-[15px] text-[14.5px] text-ink hover:bg-sand hover:text-ink">Zobacz vouchery</a>
            </div>
        @endif
    </div>
</x-shared::layout>
