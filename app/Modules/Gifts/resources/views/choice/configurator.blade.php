@inject('settings', 'App\Modules\Settings\Settings')
@inject('terms', 'App\Modules\Gifts\Support\GiftChoiceTerms')
@php
    // „Podaruj wybór” (MA-235) inside the mug configurator, which shares its size, its price list and its bottom bar.
    $t = fn (string $key, array $replace = []) => __('gifts::choice.configurator.'.$key, $replace);
    $delivery = $terms->deliveryGross();
    $nameMax = (int) $settings->get('voucher_recipient_name_max_chars', 40);
    $noteMax = (int) $settings->get('voucher_dedication_max_chars', 180);
    $eyebrow = 'text-[13.5px] font-medium text-graphite';
    $input = 'w-full min-w-0 rounded-[20px] border border-line bg-white px-[15px] py-[13px] text-[16px] text-ink placeholder:text-hint focus:border-ink';
    $radio = 'group flex cursor-pointer items-start gap-3.5 rounded-[18px] border border-line bg-white/60 px-[18px] py-4 transition-colors duration-300 hover:border-ink has-checked:border-ink has-checked:bg-sand-dark';
@endphp
<form id="gift-form" x-ref="giftForm" method="post" action="{{ route('cart.store') }}" x-on:submit.prevent="$store.cart.send($el)"
      x-show="gift" x-cloak x-init="giftDelivery = {{ $delivery }}" x-data="{ delivery: 'print', recipient: '', sender: '', dedication: '' }">
    @csrf
    <input type="hidden" name="type" value="gift_choice">
    <p class="mb-7 max-w-[46ch] text-[15px] leading-[1.65] text-pretty text-lead">{{ $t('switch_hint') }}</p>

    <fieldset class="mb-[30px]">
        <legend class="mb-3 {{ $eyebrow }}">{{ __('mug-configurator::mug.size') }}</legend>
        <div class="grid gap-2.5 sm:flex sm:flex-wrap" style="grid-template-columns: repeat({{ min(count($sizes), 3) }}, minmax(0, 1fr))">
            @foreach ($sizes as $size)
                @php
                    [$sizeName, $sizeVolume] = preg_match('/^(.*\S)\s+(\d+\s*ml)$/u', $size['name'], $parts) ? [$parts[1], $parts[2]] : [$size['name'], null];
                @endphp
                <label class="glass group flex cursor-pointer flex-col rounded-[20px] px-2 py-3 text-center sm:px-[18px] sm:text-left text-lead transition-[border-color,background-color] duration-300 hover:border-ink has-checked:border-ink has-checked:bg-ink has-checked:text-linen has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy">
                    <input type="radio" name="size" value="{{ $size['label'] }}" x-model="size" @checked($size['label'] === $startSize['label']) class="sr-only">
                    <span class="text-[13.5px]">{{ $sizeName }}@if ($sizeVolume)<span class="block sm:inline"><span class="hidden sm:inline"> </span>{{ $sizeVolume }}</span>@endif</span>
                    <span class="mt-[3px] text-[12.5px] text-label group-has-checked:text-line-strong">{{ $money($size['price_gross']) }}</span>
                </label>
            @endforeach
        </div>
    </fieldset>

    <div class="mb-[26px] rounded-[20px] border border-line bg-cream px-5 py-[22px]">
        <label for="gift-for" class="mb-1.5 block text-[13.5px] text-graphite">{{ $t('for') }}</label>
        <input id="gift-for" name="recipient_name" x-model="recipient" maxlength="{{ $nameMax }}" placeholder="{{ $t('for_placeholder') }}" autocomplete="off" aria-describedby="gift-for-hint" class="{{ $input }}">
        <div id="gift-for-hint" class="mt-2 mb-4 flex justify-between gap-3 text-[12.5px] text-hint">
            <span>{{ $t('for_hint') }}</span>
            <span class="flex-none whitespace-nowrap" x-text="recipient.length + ' / {{ $nameMax }}'">0 / {{ $nameMax }}</span>
        </div>

        <label for="gift-from" class="mb-1.5 block text-[13.5px] text-graphite">{{ $t('from') }}</label>
        <input id="gift-from" name="sender_name" x-model="sender" maxlength="{{ $nameMax }}" placeholder="{{ $t('from_placeholder') }}" autocomplete="off" class="{{ $input }} mb-4">

        <label for="gift-note" class="mb-1.5 block text-[13.5px] text-graphite">{{ $t('dedication') }}</label>
        <textarea id="gift-note" name="dedication" x-model="dedication" maxlength="{{ $noteMax }}" rows="3" placeholder="{{ $t('dedication_placeholder') }}" aria-describedby="gift-note-hint"
                  class="{{ $input }} block resize-y font-sans leading-[1.55]"></textarea>
        <div id="gift-note-hint" class="mt-2 flex justify-between gap-3 text-[12.5px] text-hint">
            <span>{{ $t('dedication_hint') }}</span>
            <span class="flex-none whitespace-nowrap" x-text="dedication.length + ' / {{ $noteMax }}'">0 / {{ $noteMax }}</span>
        </div>
    </div>

    <fieldset class="mb-[26px]">
        <legend class="mb-3 {{ $eyebrow }}">{{ $t('delivery') }}</legend>
        <div class="grid gap-2.5">
            @foreach (['print', 'email'] as $way)
                <label class="{{ $radio }}">
                    <input type="radio" name="delivery" value="{{ $way }}" x-model="delivery" @checked($way === 'print') class="sr-only">
                    <span class="mt-1 grid size-4 flex-none place-items-center rounded-full border border-label"><span class="size-2 rounded-full group-has-checked:bg-ink"></span></span>
                    <span class="min-w-0 flex-1">
                        <span class="block text-[15px]">{{ $t($way) }}</span>
                        <span class="mt-0.5 block text-[12.5px] text-label">{{ $t($way.'_hint') }}</span>
                    </span>
                </label>
            @endforeach
        </div>
        <div x-show="delivery === 'email'" x-cloak class="mt-4 grid gap-[13px]">
            <div>
                <label for="gift-email" class="mb-1.5 block text-[13.5px] text-graphite">{{ $t('recipient_email') }}</label>
                <input id="gift-email" name="recipient_email" type="email" autocomplete="off" x-bind:required="delivery === 'email'" class="{{ $input }}">
            </div>
            <div>
                <label for="gift-day" class="mb-1.5 block text-[13.5px] text-graphite">{{ $t('send_on') }}</label>
                <input id="gift-day" name="send_on" type="date" value="{{ today()->toDateString() }}" min="{{ today()->toDateString() }}"
                       max="{{ today()->addDays(\App\Modules\Gifts\Support\GiftChoiceTerms::MAX_SEND_DAYS)->toDateString() }}" class="{{ $input }}">
            </div>
        </div>
    </fieldset>

    <p class="mb-2 text-[13px] leading-[1.6] text-label">{{ $t('terms', ['days' => $terms->days()]) }}</p>
    <p class="mb-6 text-[13px] leading-[1.6] text-label" x-text="@js($t('price', ['mug' => '__MUG__', 'delivery' => $money($delivery)])).replace('__MUG__', $store.cart.format(@js($sizes->pluck('price_gross', 'label'))[size] ?? 0))">{{ $t('price', ['mug' => $money($startSize['price_gross']), 'delivery' => $money($delivery)]) }}</p>

    <button type="submit" class="fill-btn min-h-[58px] w-full rounded-full bg-ink px-[30px] py-[18px] text-[15px] font-medium tracking-[0.02em] text-linen transition duration-300 [--fill:var(--color-rose)] hover:bg-rose hover:text-ink active:scale-[.97]">
        {{ $t('add') }} &middot; <span x-text="giftPrice">{{ $money($startSize['price_gross'] + $delivery) }}</span>
    </button>
</form>
