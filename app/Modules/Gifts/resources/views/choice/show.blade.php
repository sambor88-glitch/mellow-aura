@php
    $glazes = $options->glazes();
    $ink = $options->ink();
    $position = $options->position();
    $photo = $options->photoUrl();
    $maxLines = $options->maxLines();
    $maxChars = $options->maxCharsPerLine();
    $startGlaze = $glazes->firstWhere('code', old('glaze')) ?? $glazes->first();
    $selectedShipping = old('shipping_method', $methods->keys()->first());

    $t = fn (string $key, array $replace = []) => __('gifts::choice.page.'.$key, $replace);
    $m = fn (string $key, array $replace = []) => __('mug-configurator::mug.'.$key, $replace);
    $c = fn (string $key) => __('checkout::checkout.'.$key);
    $eyebrow = 'text-[13.5px] font-medium text-graphite';
    $pill = 'glass min-h-11 rounded-full px-[14px] py-1.5 text-[12.5px] text-lead transition duration-300 hover:border-ink active:scale-[.98]';
    $panel = 'glass mb-4 rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]';
    $radio = 'group flex cursor-pointer items-center gap-3.5 rounded-[18px] border border-line bg-white/60 px-[18px] transition-colors duration-300 hover:border-ink';
@endphp
<x-shared::layout :title="$t('title')" :description="$t('description')" :canonical="route('gift-choice.index')" noindex :image="$photo">
    <div class="mx-auto max-w-[1360px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-9 pb-24"
         x-data="mugConfigurator({
             maxLines: {{ $maxLines }},
             maxChars: {{ $maxChars }},
             prices: {},
             texts: @js(__('mug-configurator::mug.js')),
             glazes: @js($glazes->keyBy('code')),
             size: @js($choice->size_label),
             glaze: @js($startGlaze['code']),
             ink: @js($ink['hex']),
         })">
        <div class="flex flex-wrap items-start gap-[clamp(28px,5vw,72px)]">
            <div class="min-w-0 flex-[1_1_440px] min-[852px]:sticky min-[852px]:top-24">
                <div class="relative overflow-hidden rounded-[26px] bg-linen">
                    @if ($photo)
                        <img src="{{ $photo }}" alt="{{ $m('alt') }}" fetchpriority="high" width="1200" height="1200" class="block aspect-square w-full object-cover">
                    @else
                        <div class="aspect-square w-full"></div>
                    @endif
                    @include('mug-configurator::partials.overlay')
                    <div class="glass absolute right-4 bottom-4 flex items-center gap-[9px] rounded-full px-[13px] py-[7px]">
                        <span aria-hidden="true" class="size-[15px] rounded-full transition-[background-color] duration-600"
                              style="background-color: {{ $startGlaze['hex'] }}" x-bind:style="{ backgroundColor: glazeHex }"></span>
                        <span class="text-[12px] text-lead">{{ $m('inside') }} <span x-text="glazeName">{{ $startGlaze['name'] }}</span></span>
                    </div>
                </div>
            </div>

            <div class="min-w-0 flex-[1_1_360px]">
                <p class="eyebrow mb-4">{{ __('gifts::choice.page.eyebrow') }} &middot; {{ $choice->size_name }}</p>
                <h1 class="mb-5 font-serif text-[length:clamp(40px,5.2vw,80px)] leading-[.94] font-light tracking-[-0.025em] text-balance">
                    <span class="blur-in">{{ $choice->recipient_name ? $t('choose_heading_named', ['name' => $choice->recipient_name]) : $t('choose_heading') }}</span>
                </h1>
                @if ($choice->dedication || $choice->sender_name)
                    <div class="mb-6 rounded-[20px] border border-divider bg-sand px-[22px] py-5">
                        @if ($choice->dedication)
                            <p class="font-serif text-[19px] leading-[1.5] whitespace-pre-line text-lead italic [overflow-wrap:anywhere]">{{ $choice->dedication }}</p>
                        @endif
                        @if ($choice->sender_name)
                            <p @class(['text-[14px] text-graphite [overflow-wrap:anywhere]', 'mt-2' => $choice->dedication])>{{ $t('from', ['name' => $choice->sender_name]) }}</p>
                        @endif
                    </div>
                @endif
                <p class="mb-8 max-w-[46ch] text-[15px] leading-[1.7] text-pretty text-lead">{{ $t('until', ['date' => $choice->choose_by->translatedFormat('j F Y')]) }}</p>

                @if (session('gift_choice_notice'))
                    <p role="alert" class="mb-6 rounded-[18px] border border-alert-line bg-alert px-4 py-3 text-[14px] text-alert-text">{{ session('gift_choice_notice') }}</p>
                @endif

                <form method="post" action="{{ $action }}" novalidate x-data="{ shipping: @js($selectedShipping) }">
                    @csrf

                    <label for="mug-text" class="mb-3 block {{ $eyebrow }}">{{ $t('your_text') }}</label>
                    <div x-ref="mini" x-cloak x-show="writing || text.trim() !== ''" aria-hidden="true"
                         class="relative mb-3 h-[150px] scroll-mt-24 overflow-hidden rounded-[18px] bg-linen [container-type:inline-size] min-[852px]:hidden">
                        <div class="absolute inset-x-0 aspect-square" style="top: clamp(calc(150px - 100cqw), calc(75px - {{ $position['y'] }}cqw), 0px)">
                            @if ($photo)
                                <img src="{{ $photo }}" alt="" loading="lazy" width="1200" height="1200" class="block aspect-square w-full object-cover">
                            @endif
                            @include('mug-configurator::partials.overlay')
                        </div>
                    </div>
                    <textarea id="mug-text" name="text" x-ref="text" x-init="setText(@js(old('text', '')))" x-on:input="typed($event)" x-on:focus="startWriting()" x-on:blur="writing = false"
                              data-enter="{{ $m('enter') }}" data-limit="{{ $m('limit', ['lines' => trans_choice('mug-configurator::mug.lines_choice', $maxLines), 'chars' => trans_choice('mug-configurator::mug.chars_choice', $maxChars)]) }}"
                              rows="{{ min(3, $maxLines) }}" autocomplete="off" spellcheck="false"
                              placeholder="{!! $m('placeholder') !!}" aria-describedby="mug-text-rules"
                              @error('text') aria-invalid="true" @enderror
                              @class([
                                  'block w-full min-w-0 resize-none rounded-[18px] border bg-white px-[18px] py-[17px] font-serif text-[24px] leading-[1.4] font-light tracking-[0.14em] text-ink uppercase transition-[border-color,box-shadow] duration-300 placeholder:text-line-strong focus:border-ink focus:shadow-[0_0_0_4px_rgb(36_65_126/.14)] focus:outline-none',
                                  'border-error' => $errors->has('text'),
                                  'border-line' => ! $errors->has('text'),
                              ])>{{ old('text') }}</textarea>
                    @error('text')
                        <p class="mt-2 text-[13px] text-error">{{ $message }}</p>
                    @enderror
                    <div class="mt-3 mb-[30px] flex flex-wrap items-center justify-between gap-3 text-[12.5px] text-label">
                        <span id="mug-text-rules">
                            <span x-text="lineInfo">{{ $m('enter') }}</span><span class="sr-only">. {{ $m('rules', ['lines' => $maxLines, 'chars' => $maxChars]) }}</span>
                            <span class="sr-only" aria-live="polite" x-text="limitNotice"></span>
                        </span>
                        <span class="flex flex-wrap items-center gap-3.5">
                            <button type="button" x-on:click="toggleSound()" class="{{ $pill }}" x-text="sound ? @js($m('mute')) : @js($m('unmute'))">{{ $m('mute') }}</button>
                            <button type="button" x-on:click="split()" class="{{ $pill }}">{{ $m('split') }}</button>
                            <span aria-hidden="true" title="{{ $m('counter_title', ['lines' => $maxLines, 'chars' => $maxChars]) }}" x-text="counter">0/{{ $maxChars }}</span>
                        </span>
                    </div>

                    <fieldset class="mb-[34px]">
                        <legend class="mb-3 {{ $eyebrow }}">{{ $m('glaze') }}</legend>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($glazes as $glaze)
                                <label title="{{ $glaze['name'] }}" style="background-color: {{ $glaze['hex'] }}"
                                       class="relative size-[42px] cursor-pointer rounded-full border-2 border-transparent shadow-[inset_0_0_0_2px_var(--color-cream)] transition-[border-color,transform] duration-300 ease-clay after:absolute after:-inset-1 hover:scale-112 has-checked:border-ink has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                                    <input type="radio" name="glaze" value="{{ $glaze['code'] }}" x-model="glaze" @checked($glaze['code'] === $startGlaze['code']) class="sr-only">
                                    <span class="sr-only">{{ $glaze['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('glaze')
                            <p class="mt-2 text-[13px] text-error">{{ $message }}</p>
                        @enderror
                    </fieldset>

                    <div class="{{ $panel }}">
                        <h2 class="mb-2 font-serif text-[28px] leading-none font-light">{{ $t('address') }}</h2>
                        <p class="mb-4 text-[13.5px] leading-[1.6] text-label">{{ $t('address_lead') }}</p>
                        <div class="grid gap-[13px]">
                            <x-shared::field name="name" :label="$c('name')" autocomplete="name" />
                            <x-shared::field name="email" :label="$c('email')" type="email" autocomplete="email" />
                            <x-shared::field name="phone" :label="$c('phone')" type="tel" autocomplete="tel" :hint="$c('phone_hint_parcel')" />
                        </div>
                    </div>

                    <fieldset class="{{ $panel }}">
                        <legend class="float-left mb-4 w-full font-serif text-[28px] leading-none font-light">{{ $c('delivery_way') }}</legend>
                        <div class="clear-both grid gap-2.5">
                            @foreach ($methods as $method)
                                <label class="{{ $radio }} py-4 has-checked:border-ink has-checked:bg-sand-dark">
                                    <input type="radio" name="shipping_method" value="{{ $method['code'] }}" x-model="shipping" @checked($selectedShipping === $method['code']) class="sr-only">
                                    <span class="grid size-4 flex-none place-items-center rounded-full border border-label"><span class="size-2 rounded-full group-has-checked:bg-ink"></span></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-[15px]">{{ $method['label'] }}</span>
                                        @if ($method['note'])
                                            <span class="mt-0.5 block text-[12.5px] text-label">{{ $method['note'] }}</span>
                                        @endif
                                    </span>
                                    <span class="text-[14px]">{{ $t('free') }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('shipping_method')
                            <p class="mt-2 text-[13px] text-error">{{ $message }}</p>
                        @enderror

                        @if ($methods->has('parcel_locker'))
                            <div x-show="shipping === 'parcel_locker'" @if ($selectedShipping !== 'parcel_locker') x-cloak @endif class="mt-4 border-t border-line pt-4">
                                <x-shared::field name="locker_code" :label="$c('locker')" :hint="$c('locker_hint')" aria-required="true"
                                                 autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="16" :placeholder="$c('locker_placeholder')" />
                            </div>
                        @endif

                        @if ($methods->has('courier'))
                            <div x-show="shipping === 'courier'" @if ($selectedShipping !== 'courier') x-cloak @endif class="mt-4 grid gap-[13px] border-t border-line pt-4">
                                <x-shared::field name="street" :label="$c('street')" autocomplete="address-line1" />
                                <div class="flex flex-wrap gap-[13px]">
                                    <x-shared::field name="postal_code" :label="$c('postal_code')" autocomplete="postal-code" inputmode="numeric" placeholder="30-001" class="flex-[0_1_130px]" />
                                    <x-shared::field name="city" :label="$c('city')" autocomplete="address-level2" class="flex-[1_1_150px]" />
                                </div>
                            </div>
                        @endif
                    </fieldset>

                    <p class="mb-4 text-[13.5px] leading-[1.6] text-brown">{{ $t('check') }}</p>
                    <button type="submit" class="fill-btn min-h-[58px] w-full rounded-full bg-ink px-[30px] py-[18px] text-[15px] font-medium tracking-[0.02em] text-linen transition duration-300 [--fill:var(--color-rose)] hover:bg-rose hover:text-ink active:scale-[.97]">{{ $t('submit') }}</button>
                    <p class="mt-3 text-[12.5px] text-label">{{ $t('privacy') }} <a href="{{ route('content.privacy') }}">Polityka prywatności</a></p>
                </form>
            </div>
        </div>
    </div>
</x-shared::layout>
