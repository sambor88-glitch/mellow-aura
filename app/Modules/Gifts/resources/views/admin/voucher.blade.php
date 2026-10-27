@use('App\Modules\Workshops\Http\Controllers\BookingController')
@use('App\Modules\Workshops\Support\WorkshopTypes')
{{-- One voucher: what it books, whether it is used, and the form to fill it in or extend it (MA-137). --}}
@php
    $formKey = 'voucher-'.$voucher->id;
    $bag = $errors->getBag($formKey);
    $workshops = app(WorkshopTypes::class)->all();
    $workshopName = $workshops->first(fn (array $workshop) => WorkshopTypes::key($workshop) === $voucher->workshop_code)['name'] ?? null;
    $booking = $voucher->redeemed_at ? $voucher->latestBooking : null;
    $field = 'min-h-11 min-w-0 rounded-[4px] border bg-white px-3 text-[14px] text-ink focus:border-ink';
@endphp
<div id="{{ $formKey }}" class="scroll-mt-6">
    <p class="text-[13px] text-label">
        @if ($voucher->redeemed_at)
            <span class="rounded-full bg-linen px-2.5 py-[3px] text-[11.5px] text-label">Wykorzystany</span>
            {{ $voucher->redeemed_at->format('j.m.Y') }}@if ($booking?->order) — zapis <a href="{{ route('admin.orders.show', $booking->order) }}">{{ $booking->order->number }}</a>@endif
        @elseif ($voucher->isExpired())
            <span class="rounded-full bg-alert px-2.5 py-[3px] text-[11.5px] text-error">Wygasł</span>
            {{ $voucher->valid_until->translatedFormat('j F Y') }} — żeby można się nim było zapisać, przesuń datę niżej.
        @else
            <span class="rounded-full bg-navy-soft px-2.5 py-[3px] text-[11.5px] text-navy">Ważny</span>
            {{ $workshopName ? 'na „'.$workshopName.'”, miejsc: '.$voucher->seats : 'bez warsztatu — tym kodem nie zapisze się przez stronę' }}
        @endif
    </p>
    @if ($bag->any())
        <p role="alert" class="mt-2 text-[13px] text-error">{{ $bag->first() }}</p>
    @endif
    <form method="post" action="{{ route('admin.gifts.voucher.update', $voucher) }}" novalidate class="mt-2.5 flex flex-wrap items-end gap-2.5">
        @csrf
        @method('PUT')
        <label class="grid min-w-0 flex-[1_1_180px] gap-1 text-[12.5px] text-label">
            Na warsztat
            <select name="workshop_code" @class([$field, 'border-error' => $bag->has('workshop_code'), 'border-line' => ! $bag->has('workshop_code')])>
                <option value="">Żaden — kwotowy</option>
                @foreach ($workshops as $workshop)
                    <option value="{{ WorkshopTypes::key($workshop) }}" @selected($voucher->workshop_code === WorkshopTypes::key($workshop))>{{ $workshop['name'] }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-[12.5px] text-label">
            Miejsc
            <input name="seats" value="{{ $voucher->seats }}" inputmode="numeric" placeholder="1" max="{{ BookingController::MAX_SEATS }}"
                   @class([$field, 'w-[64px] text-right', 'border-error' => $bag->has('seats'), 'border-line' => ! $bag->has('seats')])>
        </label>
        <label class="grid gap-1 text-[12.5px] text-label">
            Ważny do
            <input type="date" name="valid_until" value="{{ $voucher->valid_until->toDateString() }}"
                   @class([$field, 'border-error' => $bag->has('valid_until'), 'border-line' => ! $bag->has('valid_until')])>
        </label>
        <button class="min-h-11 rounded-full border border-line-strong px-4 text-[13px] text-ink hover:border-ink hover:bg-sand">Zapisz voucher</button>
    </form>
</div>
