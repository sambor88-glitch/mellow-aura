@use('App\Modules\Localization\Support\Locales')
@use('App\Modules\MugConfigurator\Support\MugAnalyticsItem')
@use('App\Modules\Shared\Support\AnalyticsItem')
@use('App\Modules\Shared\Support\Money')
@inject('settings', 'App\Modules\Settings\Settings')
@php
    $sizes = $options->sizes();
    $glazes = $options->glazes();
    $ink = $options->ink();
    $position = $options->position();
    $photo = $options->photoUrl();
    $maxLines = $options->maxLines();
    $maxChars = $options->maxCharsPerLine();
    $startGlaze = $glazes->first();

    $t = fn (string $key, array $replace = []) => __('mug-configurator::mug.'.$key, $replace);
    // Prices in the currency of the page (MugOptions::sizes). Kasia's texts from the panel, BLIK and free delivery
    // are Polish; an English page says only what it has in English.
    $currency = Locales::currency();
    $money = fn (int $amount) => Money::format($amount, $currency);
    $polish = Locales::current() === Locales::default();
    $heading = $polish ? $settings->get('text_mug_heading', $t('name')) : $t('name');
    $lead = $polish ? $settings->get('text_mug_lead') : $t('description');
    $note = $polish ? $settings->get('text_mug_note') : null;
    $leadTime = $polish ? $settings->get('text_mug_lead_time') : null;
    $bulkOrder = $polish ? $settings->get('text_mug_bulk_order') : null;
    $refusals = $polish ? $settings->get('text_mug_refusals') : null;
    $freeShipping = $polish ? $settings->get('free_shipping_threshold') : null;

    $eyebrow = 'text-[13.5px] font-medium text-graphite';
    $pill = 'glass min-h-11 rounded-full px-[14px] py-1.5 text-[12.5px] text-lead transition duration-300 hover:border-ink active:scale-[.98]';
@endphp

<x-shared::layout
    :title="$t('title')"
    :description="$t('description')"
    :canonical="route('mug.index')"
    :image="$photo"
>
    <x-slot:head>{!! $structuredData !!}</x-slot:head>
    <x-consent::analytics-event name="view_item" :params="AnalyticsItem::params($startSize['price'], [MugAnalyticsItem::make($startSize)])" />
    <div class="mx-auto max-w-[1360px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-9 pb-24"
         x-data="mugConfigurator({
             maxLines: {{ $maxLines }},
             maxChars: {{ $maxChars }},
             prices: @js($sizes->pluck('price', 'label')),
             texts: @js(__('mug-configurator::mug.js')),
             glazes: @js($glazes->keyBy('code')),
             size: @js($startSize['label']),
             glaze: @js($startGlaze['code']),
             ink: @js($ink['hex']),
         })">
        <nav aria-label="{{ $t('breadcrumb') }}" class="mb-[26px] text-[12.5px] text-label">
            <a href="{{ route('home') }}" class="text-label hover:text-navy">{{ $t('home') }}</a> &middot; <span class="text-lead">{{ $t('name') }}</span>
        </nav>

        <div class="flex flex-wrap items-start gap-[clamp(28px,5vw,72px)]">
            <div class="min-w-0 flex-[1_1_440px] min-[852px]:sticky min-[852px]:top-24">
                {{-- Two previews once the 3D mug has loaded: the model and Kasia's photo with the text on it. --}}
                <div x-cloak x-show="has3d" role="group" aria-label="{{ $t('preview') }}" class="glass mb-3 inline-flex gap-1 rounded-full p-1">
                    <button type="button" x-on:click="view = '3d'" x-bind:aria-pressed="view === '3d'"
                            class="min-h-10 rounded-full px-4 text-[13px] text-graphite transition-colors duration-300 aria-pressed:bg-ink aria-pressed:text-linen">{{ $t('model') }}</button>
                    <button type="button" x-on:click="view = 'photo'" x-bind:aria-pressed="view === 'photo'"
                            class="min-h-10 rounded-full px-4 text-[13px] text-graphite transition-colors duration-300 aria-pressed:bg-ink aria-pressed:text-linen">{{ $t('on_photo') }}</button>
                </div>
                <div x-ref="stage3d" x-cloak x-show="view === '3d'" role="img" x-bind:aria-label="@js($t('preview_label')) + (text.trim() || @js($t('preview_empty')))"
                     class="relative aspect-square w-full cursor-grab touch-pan-y overflow-hidden rounded-[26px] border border-glass-line bg-[radial-gradient(ellipse_at_50%_42%,rgb(252_249_244/.95),rgb(243_237_228/.55)_60%,rgb(237_228_216/.25))] active:cursor-grabbing">
                    <span aria-hidden="true" class="glass pointer-events-none absolute bottom-4 left-1/2 -translate-x-1/2 rounded-full px-3.5 py-2 text-[10.5px] tracking-[0.22em] whitespace-nowrap text-label uppercase">{{ $t('drag') }}</span>
                </div>
                <div x-show="view === 'photo'" class="relative overflow-hidden rounded-[26px] bg-linen">
                    @if ($photo)
                        <img src="{{ $photo }}" alt="{{ $t('alt') }}" fetchpriority="high" width="1200" height="1200" class="block aspect-square w-full object-cover">
                    @else
                        <div class="aspect-square w-full"></div>
                    @endif
                    @include('mug-configurator::partials.overlay')
                    <div class="glass absolute right-4 bottom-4 flex items-center gap-[9px] rounded-full px-[13px] py-[7px]">
                        <span aria-hidden="true" class="size-[15px] rounded-full transition-[background-color] duration-600"
                              style="background-color: {{ $startGlaze['hex'] }}" x-bind:style="{ backgroundColor: glazeHex }"></span>
                        <span class="text-[12px] text-lead">{{ $t('inside') }} <span x-text="glazeName">{{ $startGlaze['name'] }}</span></span>
                    </div>
                </div>
                @if ($note)
                    <p class="mt-3 text-[12.5px] leading-[1.6] text-hint">{{ $note }}</p>
                @endif
            </div>

            <div class="min-w-0 flex-[1_1_360px]">
                <p class="eyebrow mb-4">{{ $t('made_to_order') }} @if ($leadTime) &middot; {{ $leadTime }} @endif</p>
                <h1 class="mb-5 font-serif text-[length:clamp(44px,5.6vw,88px)] leading-[.94] font-light tracking-[-0.025em] whitespace-pre-line"><span class="blur-in">{{ $heading }}</span></h1>
                @if ($lead)
                    <p class="mb-8 max-w-[46ch] text-[16.5px] leading-[1.7] text-pretty text-lead">{{ $lead }}</p>
                @endif

                <form id="mug-form" x-ref="form" method="post" action="{{ route('cart.store') }}" x-on:submit.prevent="add($el)">
                    @csrf
                    <input type="hidden" name="type" value="mug">

                    <label for="mug-text" class="mb-3 block {{ $eyebrow }}">{{ $t('your_text') }}</label>
                    {{-- On a phone the big photo is a screen up while typing, so a strip of it waits above the field (up to 852 px, then the photo stands beside the form). --}}
                    <div x-ref="mini" x-cloak x-show="writing || text.trim() !== ''" aria-hidden="true"
                         class="relative mb-3 h-[150px] scroll-mt-24 overflow-hidden rounded-[18px] bg-linen [container-type:inline-size] min-[852px]:hidden">
                        {{-- The same square as the big preview, slid so the text sits in the middle of the strip. --}}
                        <div class="absolute inset-x-0 aspect-square" style="top: clamp(calc(150px - 100cqw), calc(75px - {{ $position['y'] }}cqw), 0px)">
                            @if ($photo)
                                <img src="{{ $photo }}" alt="" loading="lazy" width="1200" height="1200" class="block aspect-square w-full object-cover">
                            @endif
                            @include('mug-configurator::partials.overlay')
                        </div>
                    </div>
                    <textarea id="mug-text" name="text" x-ref="text" x-on:input="typed($event)" x-on:focus="startWriting()" x-on:blur="writing = false"
                              data-enter="{{ $t('enter') }}" data-limit="{{ $t('limit', ['lines' => trans_choice('mug-configurator::mug.lines_choice', $maxLines), 'chars' => trans_choice('mug-configurator::mug.chars_choice', $maxChars)]) }}"
                              rows="{{ min(3, $maxLines) }}" autocomplete="off" spellcheck="false"
                              placeholder="{!! $t('placeholder') !!}" aria-describedby="mug-text-rules"
                              class="block w-full min-w-0 resize-none rounded-[18px] border border-line bg-white px-[18px] py-[17px] font-serif text-[24px] leading-[1.4] font-light tracking-[0.14em] text-ink uppercase transition-[border-color,box-shadow] duration-300 placeholder:text-line-strong focus:border-ink focus:shadow-[0_0_0_4px_rgb(36_65_126/.14)] focus:outline-none"></textarea>
                    <div class="mt-3 mb-[30px] flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-label">
                        <span id="mug-text-rules">
                            <span x-text="lineInfo">{{ $t('enter') }}</span><span class="sr-only">. {{ $t('rules', ['lines' => $maxLines, 'chars' => $maxChars]) }}</span>
                            {{-- A letter past the limit just doesn't appear; a screen reader hears why. --}}
                            <span class="sr-only" aria-live="polite" x-text="limitNotice"></span>
                        </span>
                        <span class="flex flex-wrap items-center gap-3.5">
                            <button type="button" x-on:click="toggleSound()" class="{{ $pill }}"
                                    x-text="sound ? @js($t('mute')) : @js($t('unmute'))">{{ $t('mute') }}</button>
                            <button type="button" x-on:click="split()" class="{{ $pill }}">{{ $t('split') }}</button>
                            <span aria-hidden="true" title="{{ $t('counter_title', ['lines' => $maxLines, 'chars' => $maxChars]) }}" x-text="counter">0/{{ $maxChars }}</span>
                        </span>
                    </div>

                    <fieldset class="mb-[30px]">
                        <legend class="mb-3 {{ $eyebrow }}">{{ $t('size') }}</legend>
                        <div class="flex flex-wrap gap-2.5">
                            @foreach ($sizes as $size)
                                <label class="glass group flex cursor-pointer flex-col rounded-[20px] px-[18px] py-3 text-left text-lead transition-[border-color,background-color] duration-300 hover:border-ink has-checked:border-ink has-checked:bg-ink has-checked:text-linen has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                    <input type="radio" name="size" value="{{ $size['label'] }}" x-model="size" @checked($size['label'] === $startSize['label']) class="sr-only">
                                    <span class="text-[13.5px]">{{ $size['name'] }}</span>
                                    <span class="mt-[3px] text-[12.5px] text-label group-has-checked:text-line-strong">{{ $money($size['price']) }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <fieldset class="mb-[34px]">
                        <legend class="mb-3 {{ $eyebrow }}">{{ $t('glaze') }}</legend>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($glazes as $glaze)
                                <label title="{{ $glaze['name'] }}" style="background-color: {{ $glaze['hex'] }}"
                                       class="relative size-[42px] cursor-pointer rounded-full border-2 border-transparent shadow-[inset_0_0_0_2px_var(--color-cream)] transition-[border-color,transform] duration-300 ease-clay after:absolute after:-inset-1 hover:scale-112 has-checked:border-ink has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                    <input type="radio" name="glaze" value="{{ $glaze['code'] }}" x-model="glaze" @checked($glaze['code'] === $startGlaze['code']) class="sr-only">
                                    <span class="sr-only">{{ $glaze['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>

                    <button type="submit" data-magnet class="fill-btn min-h-[58px] w-full rounded-full bg-ink px-[30px] py-[18px] text-[15px] font-medium tracking-[0.02em] text-linen transition duration-300 [--fill:var(--color-rose)] hover:bg-rose hover:text-ink active:scale-[.97]">
                        {{ $t('add') }} &middot; <span x-text="price">{{ $money($startSize['price']) }}</span>
                    </button>
                </form>

                @if ($polish)
                    <div class="mt-4 flex items-center gap-2.5 text-[12.5px] text-muted">
                        <span class="rounded-full bg-navy px-2.5 py-[3px] text-[10px] font-semibold text-white">BLIK</span>
                        <span>{{ $t('pay') }}{{ $freeShipping ? ' · '.$t('free_from', ['price' => $money((int) $freeShipping)]) : '' }}</span>
                    </div>
                @endif

                @if ($bulkOrder || $refusals)
                    <div class="mt-8 grid grid-cols-[repeat(auto-fit,minmax(170px,1fr))] gap-2.5 text-[13.5px] leading-[1.55] text-muted [&>div]:glass [&>div]:rounded-[18px] [&>div]:px-[18px] [&>div]:py-4">
                        @if ($bulkOrder)
                            <div>
                                <div class="mb-1 text-ink">{{ $t('bulk') }}</div>
                                {{ $bulkOrder }}
                            </div>
                        @endif
                        @if ($refusals)
                            <div>
                                <div class="mb-1 text-ink">{{ $t('refusals') }}</div>
                                {{ $refusals }}
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>

        {{-- On a phone the price and the button stay at the bottom (mellowaura-aplikacja), out of the way while typing
             and while the form's own button is in view. Empty, the button says what is missing. --}}
        <div x-data="buyBar('#mug-form [type=submit]')" x-cloak x-bind:class="(shown && ! writing) || 'invisible translate-y-full'"
             class="glass sticky bottom-[calc(12px+env(safe-area-inset-bottom))] z-50 mt-10 flex items-center gap-3 rounded-full py-2 pr-2 pl-5 transition duration-500 ease-clay min-[852px]:hidden">
            <div class="min-w-0 flex-auto">
                <div class="font-serif text-[22px] leading-none" x-text="price">{{ $money($startSize['price']) }}</div>
                <div class="mt-1 truncate text-[11.5px] text-label" x-text="@js($sizes->pluck('title', 'label'))[size] + ' · ' + @js($t('inside')) + ' ' + glazeName">{{ $startSize['title'] }} · {{ $t('inside') }} {{ $startGlaze['name'] }}</div>
            </div>
            <button type="button" x-on:click="text.trim() ? $refs.form.requestSubmit() : focusText()" x-text="text.trim() ? @js($t('to_basket')) : @js($t('type_text'))"
                    class="fill-btn flex-none rounded-full bg-ink px-6 py-[14px] text-[14px] text-linen transition duration-300 [--fill:var(--color-rose)] hover:bg-rose hover:text-ink active:scale-[.97]">{{ $t('type_text') }}</button>
        </div>
    </div>
</x-shared::layout>
