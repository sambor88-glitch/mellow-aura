@use('App\Modules\Gifts\Enums\GiftChoiceStatus')
@use('App\Modules\Shared\Support\Money')
@php
    $status = $choice->status();
    $buyer = $choice->orderItem->order;
    $pill = 'inline-flex min-h-11 items-center rounded-full border border-line-strong px-4 text-[13px] text-ink hover:border-ink hover:bg-sand-dark';
@endphp
<article id="karta-{{ $choice->id }}" class="flex flex-wrap items-start gap-x-5 gap-y-3 rounded-[4px] border border-line bg-cream px-[18px] py-4">
    <div class="flex-none font-serif text-[17px] tracking-[0.06em] tabular-nums select-all">{{ $choice->code }}</div>
    <div class="min-w-0 flex-[1_1_260px]">
        <h3 class="text-[14.5px] text-ink">
            {{ $choice->size_name }}{{ $choice->recipient_name ? ' · dla: '.$choice->recipient_name : '' }}
        </h3>
        <div class="mt-1 text-[13px] text-lead">
            Kupiła {{ $buyer->name }} · <a href="{{ route('admin.orders.show', $buyer) }}">{{ $buyer->number }}</a>
            · {{ $choice->goesByEmail() ? 'e-mailem na '.$choice->recipient_email.' '.$choice->send_on->format('j.m.Y') : 'karta do druku' }}
        </div>
        <div class="mt-1 text-[12.5px] text-label tabular-nums">
            @if ($choice->chosen_at)
                Wybrane {{ $choice->chosen_at->format('j.m.Y') }}
                @if ($choice->fulfilmentOrder)
                    · <a href="{{ route('admin.orders.show', $choice->fulfilmentOrder) }}">zamówienie {{ $choice->fulfilmentOrder->number }}</a>
                @endif
            @elseif ($choice->resolution)
                {{ $choice->resolution->label() }} · {{ $choice->resolved_at?->format('j.m.Y') }}{{ $choice->voucher ? ' · voucher '.$choice->voucher->code : '' }}
            @else
                Wybór do {{ $choice->choose_by?->format('j.m.Y') }}{{ $choice->reminders_sent > 0 ? ' · przypomnienia: '.$choice->reminders_sent : '' }}
            @endif
        </div>
    </div>
    <div class="flex flex-none flex-wrap items-center gap-2.5">
        <span class="rounded-full px-3 py-[5px] text-[11.5px] {{ $status->chipClass() }}">{{ $status->label() }}</span>
        @if ($decide)
            <form method="post" action="{{ route('admin.gift-choices.resolve', $choice) }}" x-data="{ asking: '' }" class="flex flex-wrap items-center gap-2.5">
                @csrf
                <button type="button" x-show="! asking" x-on:click="asking = 'plain_mug'" class="{{ $pill }}">Kubek bez napisu</button>
                <button type="button" x-show="! asking" x-on:click="asking = 'voucher'" class="{{ $pill }}">Zamień na voucher</button>
                <span x-show="asking" x-cloak class="text-[13px] text-graphite" x-text="asking === 'voucher' ? @js('Voucher na '.Money::format($choice->price_gross).' pójdzie do kupującej.') : @js('Karta będzie załatwiona, adres ustalisz z kupującą.')"></span>
                <button x-show="asking" x-cloak name="resolution" x-bind:value="asking" class="min-h-11 rounded-full bg-ink px-4 text-[13px] text-linen hover:bg-rose hover:text-ink">Tak, zapisz</button>
                <button type="button" x-show="asking" x-cloak x-on:click="asking = ''" class="text-[13px] text-label">Wróć</button>
            </form>
        @elseif (in_array($status, [GiftChoiceStatus::Waiting, GiftChoiceStatus::Scheduled], true))
            <form method="post" action="{{ route('admin.gift-choices.resend', $choice) }}">
                @csrf
                <button class="{{ $pill }}">{{ $choice->goesByEmail() ? ($choice->sent_at ? 'Wyślij jeszcze raz' : 'Wyślij teraz') : 'Wyślij PDF kupującej' }}</button>
            </form>
        @endif
    </div>
</article>
