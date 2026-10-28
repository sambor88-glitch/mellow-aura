@use('App\Modules\Admin\Support\PanelAccess')
@use('App\Modules\Gifts\Enums\RedemptionStatus')
@use('App\Modules\Shared\Support\Money')
@use('App\Modules\Workshops\Enums\BookingStatus')
@use('App\Modules\Workshops\Http\Controllers\BookingController')
@use('App\Modules\Workshops\Support\WorkshopTypes')
{{-- One voucher: what it books, whether it is used, and the form to fill it in, extend it or mark it used (MA-137).
     An amount voucher shows its balance and every use of it instead, and only its date can change (MA-211). --}}
@php
    $formKey = 'voucher-'.$voucher->id;
    $bag = $errors->getBag($formKey);
    // After a mistake the form keeps what Kasia typed, in this voucher's form only.
    $mine = old('form') === $formKey;
    $old = fn (string $key, mixed $saved) => $mine ? old($key) : $saved;
    $workshops ??= app(WorkshopTypes::class)->all();
    $workshopName = $workshops->first(fn (array $workshop) => WorkshopTypes::key($workshop) === $voucher->workshop_code)['name'] ?? null;
    $booking = $voucher->redeemed_at ? $voucher->latestBooking : null;
    $booked = $booking?->status === BookingStatus::Confirmed;
    $workshopCode = (string) $old('workshop_code', $voucher->workshop_code);
    $field = 'min-h-11 min-w-0 rounded-[4px] border bg-white px-3 text-[14px] text-ink focus:border-ink';
@endphp
@if ($voucher->isAmount())
    <div id="{{ $formKey }}" class="scroll-mt-6">
        <p class="text-[13px] text-label">
            @if ($voucher->isExpired())
                <span class="rounded-full bg-alert px-2.5 py-[3px] text-[11.5px] text-error">Wygasł</span>
                {{ $voucher->valid_until->translatedFormat('j F Y') }} — żeby można nim było zapłacić, przesuń datę niżej.
            @elseif ($voucher->balance_minor > 0)
                <span class="rounded-full bg-navy-soft px-2.5 py-[3px] text-[11.5px] text-navy">Ważny</span>
                kwotowy — płaci w kasie sklepu, także w częściach
            @else
                <span class="rounded-full bg-linen px-2.5 py-[3px] text-[11.5px] text-label">Wykorzystany</span>
                nic na nim nie zostało
            @endif
        </p>
        <p class="mt-2 font-serif text-[19px] tabular-nums">Saldo {{ Money::format($voucher->balance_minor) }} <span class="text-[14px] text-label">z {{ Money::format($voucher->amount_minor) }}</span></p>
        @if ($voucher->redemptions->isEmpty())
            <p class="mt-1 text-[13px] text-label">Jeszcze nim nie płacono.</p>
        @else
            <ul class="mt-1.5 grid gap-1 text-[13px]">
                @foreach ($voucher->redemptions as $redemption)
                    <li class="flex flex-wrap justify-between gap-x-3 border-b border-divider py-1.5 last:border-b-0">
                        <span>
                            {{ ($redemption->returned_at ?? $redemption->used_at ?? $redemption->created_at)->format('j.m.Y, H:i') }} ·
                            @if ($redemption->order)
                                <a href="{{ route('admin.orders.show', $redemption->order) }}">{{ $redemption->order->number }}</a>
                            @else
                                zamówienie usunięte
                            @endif
                            · <span class="text-label">{{ $redemption->status->label() }}</span>
                        </span>
                        <span @class(['tabular-nums', 'text-label line-through' => $redemption->status === RedemptionStatus::Returned])>−&thinsp;{{ Money::format($redemption->amount_minor) }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
        @if (PanelAccess::allows(auth()->user(), 'admin.gifts.voucher.update'))
            @if ($bag->any())
                <p role="alert" class="mt-2 text-[13px] text-error">{{ $bag->first() }}</p>
            @endif
            <form method="post" action="{{ route('admin.gifts.voucher.update', $voucher) }}" novalidate class="mt-2.5 flex flex-wrap items-end gap-2.5">
                @csrf
                @method('PUT')
                <input type="hidden" name="form" value="{{ $formKey }}">
                <label class="grid gap-1 text-[12.5px] text-label">
                    Ważny do
                    <input type="date" name="valid_until" value="{{ $old('valid_until', $voucher->valid_until->toDateString()) }}"
                           @class([$field, 'border-error' => $bag->has('valid_until'), 'border-line' => ! $bag->has('valid_until')])>
                </label>
                <button class="min-h-11 rounded-full border border-line-strong px-4 text-[13px] text-ink hover:border-ink hover:bg-sand">Przesuń datę</button>
            </form>
        @endif
    </div>
@else
<div id="{{ $formKey }}" class="scroll-mt-6">
    <p class="text-[13px] text-label">
        @if ($voucher->redeemed_at)
            <span class="rounded-full bg-linen px-2.5 py-[3px] text-[11.5px] text-label">Wykorzystany</span>
            {{ $voucher->redeemed_at->format('j.m.Y') }} —
            @if ($booked && $booking->order)
                zapis <a href="{{ route('admin.orders.show', $booking->order) }}">{{ $booking->order->number }}</a>
            @else
                oznaczony ręcznie
            @endif
        @elseif ($voucher->isExpired())
            <span class="rounded-full bg-alert px-2.5 py-[3px] text-[11.5px] text-error">Wygasł</span>
            {{ $voucher->valid_until->translatedFormat('j F Y') }} — żeby można się nim było zapisać, przesuń datę niżej.
        @else
            <span class="rounded-full bg-navy-soft px-2.5 py-[3px] text-[11.5px] text-navy">Ważny</span>
            {{ $workshopName ? 'na „'.$workshopName.'”, miejsc: '.$voucher->seats : 'bez warsztatu — tym kodem nie zapisze się przez stronę' }}
        @endif
    </p>
    @if (PanelAccess::allows(auth()->user(), 'admin.gifts.voucher.update'))
        @if ($bag->any())
            <p role="alert" class="mt-2 text-[13px] text-error">{{ $bag->first() }}</p>
        @endif
        <form method="post" action="{{ route('admin.gifts.voucher.update', $voucher) }}" novalidate class="mt-2.5 flex flex-wrap items-end gap-2.5">
            @csrf
            @method('PUT')
            <input type="hidden" name="form" value="{{ $formKey }}">
            <label class="grid min-w-0 flex-[1_1_180px] gap-1 text-[12.5px] text-label">
                Na warsztat
                <select name="workshop_code" @class([$field, 'border-error' => $bag->has('workshop_code'), 'border-line' => ! $bag->has('workshop_code')])>
                    <option value="">Żaden — kwotowy</option>
                    @foreach ($workshops as $workshop)
                        <option value="{{ WorkshopTypes::key($workshop) }}" @selected($workshopCode === WorkshopTypes::key($workshop))>{{ $workshop['name'] }}</option>
                    @endforeach
                </select>
            </label>
            <label class="grid gap-1 text-[12.5px] text-label">
                Miejsc
                <input name="seats" value="{{ $old('seats', $voucher->seats) }}" inputmode="numeric" placeholder="1" max="{{ BookingController::MAX_SEATS }}"
                       @class([$field, 'w-[64px] text-right', 'border-error' => $bag->has('seats'), 'border-line' => ! $bag->has('seats')])>
            </label>
            <label class="grid gap-1 text-[12.5px] text-label">
                Ważny do
                <input type="date" name="valid_until" value="{{ $old('valid_until', $voucher->valid_until->toDateString()) }}"
                       @class([$field, 'border-error' => $bag->has('valid_until'), 'border-line' => ! $bag->has('valid_until')])>
            </label>
            @if ($booked)
                <input type="hidden" name="redeemed" value="1">
            @else
                <label class="flex min-h-11 items-center gap-2 text-[13px] text-graphite">
                    <input type="checkbox" name="redeemed" value="1" @checked($mine ? old('redeemed') : $voucher->redeemed_at !== null) class="size-4 accent-ink">
                    wykorzystany — zapisałam ręcznie
                </label>
            @endif
            <button class="min-h-11 rounded-full border border-line-strong px-4 text-[13px] text-ink hover:border-ink hover:bg-sand">Zapisz voucher</button>
        </form>
    @endif
</div>
@endif
