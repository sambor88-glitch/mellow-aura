@use('App\Modules\Workshops\Enums\BookingStatus')
@php
    $people = fn (int $count) => $count.' '.($count === 1 ? 'osoba' : (in_array($count % 10, [2, 3, 4], true) && ! in_array($count % 100, [12, 13, 14], true) ? 'osoby' : 'osób'));
    $taken = $date->seatsTaken();
@endphp
<x-admin::layout title="Zapisy" :lead="collect([$workshop ?? 'warsztatu nie ma już w cenniku', $date->starts_at->translatedFormat('l, j F'), $date->timeLabel()])->join(' · ')">
    <div class="mb-6 flex flex-wrap items-baseline justify-between gap-x-5 gap-y-2">
        <a href="{{ route('admin.workshops.edit') }}#terminy" class="text-[13px]">← Terminarz</a>
        <p class="text-[13.5px] text-graphite tabular-nums">Zajęte {{ $taken }} z {{ $date->seats }} · {{ $date->seatsLeftLabel() }}</p>
    </div>

    @if ($active->isEmpty())
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">Nikt się jeszcze nie zapisał</div>
            <p class="mx-auto max-w-[44ch] text-[13.5px] text-label">Gdy ktoś zapisze się na ten termin przez stronę, zobaczysz go tutaj razem z kontaktem.</p>
        </div>
    @else
        <ul class="grid gap-2.5">
            @foreach ($active as $booking)
                @php($order = $booking->order)
                <li id="zapis-{{ $booking->id }}" class="grid scroll-mt-6 gap-y-3 rounded-[4px] border border-line bg-cream px-[18px] py-4">
                    <div class="flex flex-wrap items-start gap-x-5 gap-y-2">
                        <div class="min-w-0 flex-[1_1_260px]">
                            <h2 class="text-[14.5px] text-ink">{{ $order->name }} <span class="text-[12.5px] text-label tabular-nums">· {{ $people($booking->seats) }}</span></h2>
                            <div class="mt-1 text-[13px] text-lead [overflow-wrap:anywhere]">
                                <a href="mailto:{{ $order->email }}">{{ $order->email }}</a>@if ($order->phone) · <a href="tel:{{ preg_replace('/[^\d+]/', '', $order->phone) }}">{{ $order->phone }}</a>@endif
                            </div>
                            <div class="mt-1 text-[12.5px] text-muted tabular-nums">
                                <a href="{{ route('admin.orders.show', $order) }}">{{ $order->number }}</a> · {{ $booking->paidLabel() }} · zapis {{ $booking->created_at->format('j.m, H:i') }}
                            </div>
                        </div>
                        <span class="flex-none rounded-full px-3 py-[5px] text-[11.5px] {{ $booking->status->chipClass() }}">{{ $booking->status->label() }}</span>
                    </div>

                    <form method="post" action="{{ route('admin.workshops.bookings.cancel', $booking) }}"
                          x-data x-on:submit="confirm(@js('Anulować zapis: '.$order->name.'? Miejsce od razu wróci do zapisów.')) || $event.preventDefault()"
                          class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-divider pt-3">
                        @csrf
                        @if ($booking->status === BookingStatus::Confirmed && $booking->voucher)
                            <label class="flex min-h-11 items-center gap-2 text-[13px] text-graphite">
                                <input type="checkbox" name="notify" value="1" checked class="size-4 accent-ink">
                                Wyślij klientce maila, że voucher znów jest ważny
                            </label>
                        @elseif ($booking->status === BookingStatus::Confirmed)
                            <label class="flex min-h-11 items-center gap-2 text-[13px] text-graphite">
                                <input type="checkbox" name="notify" value="1" checked class="size-4 accent-ink">
                                Wyślij klientce maila o anulowaniu i zwrocie
                            </label>
                        @elseif ($booking->status === BookingStatus::Overbooked)
                            <p class="text-[13px] text-muted">Klientka dostała już maila o zwrocie.</p>
                        @else
                            <p class="text-[13px] text-muted">Jeszcze nie zapłaciła — nie ma czego zwracać.</p>
                        @endif
                        <button class="ml-auto min-h-11 rounded-full border border-line-strong px-4 text-[13px] text-lead hover:border-error hover:text-error">Anuluj zapis</button>
                    </form>
                </li>
            @endforeach
        </ul>
        @if ($active->contains(fn ($booking) => $booking->status->isPaid() && ! $booking->voucher))
            <p class="mt-4 max-w-[62ch] text-[13px] leading-[1.6] text-muted">Anulowanie opłaconego zapisu nie oddaje pieniędzy samo — zamówienie trafia do „Problem” z kwotą do zwrotu. Po przelewie zmień jego status na „Zakończone”.</p>
        @endif
    @endif

    @if ($inactive->isNotEmpty())
        <details class="mt-8">
            <summary class="cursor-pointer text-[13px] text-label">Anulowane i niezapłacone ({{ $inactive->count() }})</summary>
            <ul class="mt-3 overflow-hidden rounded-[4px] border border-line bg-white">
                @foreach ($inactive as $booking)
                    <li class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-line px-4 py-3 text-[13px] last:border-b-0">
                        <span class="min-w-0 flex-[1_1_200px] text-ink">{{ $booking->order->name }} <span class="text-label">· {{ $people($booking->seats) }} · <a href="{{ route('admin.orders.show', $booking->order) }}">{{ $booking->order->number }}</a></span></span>
                        <span class="text-muted tabular-nums">{{ $booking->status === BookingStatus::Held ? 'Nie zapłaciła w czasie' : $booking->status->label().($booking->cancelled_at ? ' '.$booking->cancelled_at->format('j.m, H:i') : '') }}</span>
                    </li>
                @endforeach
            </ul>
        </details>
    @endif
</x-admin::layout>
