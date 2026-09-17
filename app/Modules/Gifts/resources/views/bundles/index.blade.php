@use('App\Modules\Shared\Support\Money')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $heading = $settings->get('text_bundles_heading', 'Zestawy prezentowe');
    $lead = $settings->get('text_bundles_lead');
    $wrapHeading = $settings->get('text_gift_wrap_heading', 'Pakowanie na prezent');
    $wrapLead = $settings->get('text_gift_wrap_lead');
@endphp

<x-shared::layout
    title="Zestawy prezentowe — ceramika i jedwab w pudełku"
    description="Gotowe zestawy: kubek i scrunchie w jednym kolorze, talerz z filiżanką, kadzielnica z podstawką. Taniej niż osobno, zapakowane na prezent."
    :canonical="route('bundles.index')"
    :image="$bundles->first()?->items->first()?->variant->product->getFirstMedia('images')?->getAvailableUrl(['card'])"
>
    <div class="mx-auto max-w-[1280px] animate-ma-view px-7 pt-14 pb-24">
        <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">zestawy prezentowe</div>
        <h1 class="mb-[18px] font-serif text-[length:clamp(38px,5.2vw,68px)] leading-[1.04] font-light tracking-[-0.02em] whitespace-pre-line">{{ $heading }}</h1>
        @if ($lead)
            <p class="mb-11 max-w-[56ch] text-[17px] leading-[1.7] text-lead">{{ $lead }}</p>
        @endif

        <div class="grid gap-[26px]">
            @forelse ($bundles as $bundle)
                @php
                    $full = $bundle->fullPrice();
                    $price = $bundle->price();
                @endphp
                <article id="zestaw-{{ $bundle->id }}" class="flex scroll-mt-28 animate-ma-up flex-wrap overflow-hidden rounded-[4px] border border-divider bg-cream">
                    <div class="flex min-w-0 flex-[1_1_260px]">
                        @foreach ($bundle->items->take(2) as $item)
                            @php($photo = $item->variant->product->getFirstMedia('images'))
                            @if ($photo)
                                <img src="{{ $photo->getAvailableUrl(['card']) }}" alt="{{ $photo->getCustomProperty('alt') ?: $item->variant->product->name }}" loading="lazy"
                                     class="block aspect-square w-1/2 bg-line-soft object-cover">
                            @else
                                <div class="aspect-square w-1/2 bg-line-soft"></div>
                            @endif
                        @endforeach
                    </div>
                    <div class="flex min-w-0 flex-[1_1_340px] flex-col justify-center px-[30px] py-8">
                        <h2 class="mb-3 font-serif text-[length:clamp(24px,2.8vw,32px)] leading-[1.16] font-normal">{{ $bundle->name }}</h2>
                        @if ($bundle->description)
                            <p class="mb-[18px] max-w-[52ch] text-[15.5px] leading-[1.64] text-lead">{{ $bundle->description }}</p>
                        @endif
                        <ul class="mb-5 flex flex-wrap gap-2" aria-label="W zestawie">
                            @foreach ($bundle->items as $item)
                                <li class="rounded-full bg-sand-dark px-[13px] py-1.5 text-[12px] tracking-[0.06em] text-muted">{{ $item->variant->product->name }}</li>
                            @endforeach
                        </ul>
                        <form method="post" action="{{ route('cart.store') }}" x-data x-on:submit.prevent="$store.cart.send($el)" class="flex flex-wrap items-center gap-4">
                            @csrf
                            <input type="hidden" name="type" value="bundle">
                            <input type="hidden" name="bundle_id" value="{{ $bundle->id }}">
                            <span class="font-serif text-[27px]">{{ Money::format($price) }}</span>
                            @if ($price < $full)
                                <span class="text-[15px] text-hint line-through"><span class="sr-only">osobno </span>{{ Money::format($full) }}</span>
                                <span class="rounded-full bg-navy px-3 py-[5px] text-[11.5px] tracking-[0.1em] text-linen uppercase">taniej o {{ Money::format($full - $price) }}</span>
                            @endif
                            <button class="ml-auto min-h-11 rounded-full bg-ink px-7 py-3.5 text-[14px] text-linen transition duration-300 hover:bg-navy active:scale-[.97]">
                                Do koszyka<span class="sr-only">: {{ $bundle->name }}</span>
                            </button>
                        </form>
                    </div>
                </article>
            @empty
                <div class="rounded-[4px] bg-sand-dark px-9 py-11 text-center">
                    <h2 class="mb-2 font-serif text-[26px] font-normal">Zestawy wrócą, gdy wyjdą z pieca</h2>
                    <p class="mb-[22px] text-[15.5px] text-lead">Części z zestawów pojechały już do kogoś. Każdą rzecz ze sklepu kupisz też osobno.</p>
                    <a href="{{ route('shop.index') }}" class="inline-block rounded-full bg-ink px-7 py-3.5 text-[14px] text-linen hover:bg-navy hover:text-linen">Zobacz produkty</a>
                </div>
            @endforelse
        </div>

        @if ($wrapOffered)
            <div class="mt-11 flex flex-wrap items-center gap-7 rounded-[4px] bg-sand-dark px-8 py-9">
                <div class="min-w-0 flex-[1_1_320px]">
                    <h2 class="mb-2 font-serif text-[26px] font-normal">{{ $wrapHeading }}</h2>
                    @if ($wrapLead)
                        <p class="max-w-[52ch] text-[15px] leading-[1.62] text-lead">{{ $wrapLead }}</p>
                    @endif
                </div>
                {{-- Wrapping is one line in the cart: one form adds it, the other takes it back out. --}}
                <div x-data="{ wrapped: @js($wrapped) }" class="flex-none">
                    <form method="post" action="{{ route('cart.store') }}" x-show="! wrapped" @if ($wrapped) x-cloak @endif
                          x-on:submit.prevent="(await $store.cart.send($el)) && (wrapped = true)">
                        @csrf
                        <input type="hidden" name="type" value="gift_wrap">
                        <button class="min-h-11 rounded-full border border-line-strong bg-cream px-[26px] py-3.5 text-[14px] text-ink transition duration-300 hover:border-ink active:scale-[.98]">
                            Zapakuj na prezent — {{ Money::format($wrapPrice) }}
                        </button>
                    </form>
                    <form method="post" action="{{ route('cart.destroy', 'wrap') }}" x-show="wrapped" @unless ($wrapped) x-cloak @endunless
                          x-on:submit.prevent="(await $store.cart.send($el)) && (wrapped = false)">
                        @csrf
                        @method('DELETE')
                        <button class="min-h-11 rounded-full border border-ink bg-cream px-[26px] py-3.5 text-[14px] text-ink transition duration-300 hover:bg-sand active:scale-[.98]">
                            <span aria-hidden="true" class="text-success">✓</span> Pakowanie dodane · usuń
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-shared::layout>
