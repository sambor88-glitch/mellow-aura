@use('App\Modules\Checkout\Enums\ComplaintStatus')
@use('App\Modules\Checkout\Enums\PaymentStatus')
@use('App\Modules\Shared\Support\Money')
@php
    $customer = $records->customer;
    $blockers = $records->blockers();
    $card = 'rounded-[4px] border border-line bg-cream px-[18px] py-4';
    $heading = 'mb-3 font-serif text-[24px]';
    $button = 'min-h-11 rounded-full border border-line-strong px-5 text-[13px] text-ink hover:border-ink hover:bg-sand';
    $empty = 'rounded-[4px] border border-dashed border-line-strong bg-linen px-5 py-4 text-[13px] text-label';
    // The contact on top, the history in tabs (MA-326); „Odstąpienia i reklamacje” only when there are any.
    $hasCases = $records->withdrawals->isNotEmpty() || $records->receivedComplaints->isNotEmpty() || $records->complaints->isNotEmpty();
    $tabs = array_filter([
        'zamowienia' => ['label' => 'Zamówienia i warsztaty', 'count' => $records->orders->count()],
        'pomysly' => ['label' => 'Pomysły i wypały', 'count' => $records->customOrders->count() + $records->firingRequests->count()],
        'sprawy' => $hasCases ? 'Odstąpienia i reklamacje' : null,
        'rodo' => 'Dane i RODO',
    ]);
@endphp
<x-admin::layout :title="$customer->name">
    <a href="{{ route('admin.customers.index') }}" class="mb-3 inline-flex min-h-11 items-center text-[13px] text-lead">← Wszyscy klienci</a>

    <div class="{{ $card }} mb-5 flex flex-wrap items-center gap-x-6 gap-y-3">
        <div class="min-w-0 flex-[1_1_260px]">
            <div class="text-[14.5px] text-ink [overflow-wrap:anywhere]">{{ $customer->email }}</div>
            @if ($customer->phones !== [])
                <div class="mt-0.5 text-[13px] text-label">{{ implode(', ', $customer->phones) }}</div>
            @endif
            <div class="mt-1 text-[12.5px] text-hint">{{ $customer->activity() }}</div>
        </div>
        <form method="post" action="{{ route('admin.customers.export', $customer->key) }}" class="flex-none">
            @csrf
            <button class="{{ $button }}">Pobierz kopię jej danych</button>
        </form>
    </div>

  <x-admin::tabs :tabs="$tabs" label="Historia klienta">
    <x-admin::tab-panel name="zamowienia">
    {{-- The newest five first; the rest wait under „Pokaż wszystkie”, so the tab stays short for a regular customer (MA-326). --}}
    <section aria-labelledby="zamowienia" class="mb-10" x-data="{ all: false }">
        <h2 id="zamowienia" class="sr-only">Zamówienia i warsztaty</h2>
        @forelse ($records->orders as $order)
            @php($booking = $order->workshopBooking)
            <div class="{{ $card }} mb-2.5 flex flex-wrap items-center gap-3.5" @if ($loop->index >= 5) x-show="all" style="display: none" @endif>
                <div class="flex-none text-[12.5px] text-hint tabular-nums">
                    <div>{{ $order->number }}</div>
                    <div>{{ $order->created_at->format('j.m.Y') }}</div>
                </div>
                <div class="min-w-0 flex-[1_1_220px]">
                    <div class="text-[13.5px] text-ink">{{ $order->items->map(fn ($item) => $item->product_name.($item->variant_label === '' ? '' : ' ('.$item->variant_label.')').' × '.$item->quantity)->join(', ') }}</div>
                    @if ($booking?->workshopDate)
                        <div class="mt-0.5 text-[12.5px] text-label">Warsztat {{ $booking->workshopDate->dayLabel() }}, {{ $booking->workshopDate->timeLabel() }} · {{ mb_strtolower($booking->status->label()) }}</div>
                    @endif
                </div>
                <div class="flex-none font-serif text-[19px] tabular-nums">{{ $order->money($order->total_gross) }}</div>
                <span class="flex-none rounded-full px-3 py-[5px] text-[11.5px] {{ $order->payment_status === PaymentStatus::Paid ? $order->status->chipClass() : 'bg-linen text-label' }}">{{ $order->payment_status === PaymentStatus::Paid ? $order->status->label() : $order->payment_status->label() }}</span>
                <a href="{{ route('admin.orders.show', $order) }}" class="inline-flex min-h-11 flex-none items-center rounded-full border border-line-strong px-4 text-[12.5px] text-ink hover:border-ink hover:bg-sand-dark hover:text-ink">Szczegóły</a>
            </div>
        @empty
            <p class="{{ $empty }}">Żadnego zamówienia.</p>
        @endforelse
        @if ($records->orders->count() > 5)
            <button type="button" x-show="! all" x-on:click="all = true" class="{{ $button }}">Pokaż wszystkie ({{ $records->orders->count() }})</button>
        @endif

        @if ($records->vouchers->isNotEmpty())
            <p class="mt-3 text-[13px] text-label">Kupione vouchery: {{ $records->vouchers->map(fn ($voucher) => $voucher->code.($voucher->recipient_name ? ' dla '.$voucher->recipient_name : ''))->join(', ') }}</p>
        @endif
    </section>

    </x-admin::tab-panel>

    <x-admin::tab-panel name="pomysly">
    <section aria-labelledby="indywidualne" class="mb-10">
        <h2 id="indywidualne" class="{{ $heading }}">Zamówienia indywidualne</h2>
        @forelse ($records->customOrders as $idea)
            <div class="{{ $card }} mb-2.5 flex flex-wrap items-center gap-3.5">
                <div class="flex-none text-[12.5px] text-hint tabular-nums">{{ $idea->created_at->format('j.m.Y') }}</div>
                <div class="min-w-0 flex-[1_1_260px]">
                    <div class="text-[13.5px] text-ink">{{ collect($idea->summary())->map(fn ($value, $label) => $label.': '.$value)->join(' · ') }}</div>
                    <p class="mt-1 line-clamp-2 text-[12.5px] text-label">{{ $idea->description }}</p>
                </div>
                @if ($idea->quote !== null)
                    <div class="flex-none font-serif text-[19px] tabular-nums">{{ Money::format($idea->quote) }}</div>
                @endif
                <span class="flex-none rounded-full px-3 py-[5px] text-[11.5px] {{ $idea->status->chipClass() }}">{{ $idea->status->label() }}</span>
                <a href="{{ route('admin.custom-orders.index', ['status' => $idea->status->value]) }}#pomysl-{{ $idea->id }}" class="inline-flex min-h-11 flex-none items-center rounded-full border border-line-strong px-4 text-[12.5px] text-ink hover:border-ink hover:bg-sand-dark hover:text-ink">Szczegóły</a>
            </div>
        @empty
            <p class="{{ $empty }}">Żadnego pomysłu z formularza.</p>
        @endforelse
    </section>

    <section aria-labelledby="wypaly" class="mb-10">
        <h2 id="wypaly" class="{{ $heading }}">Zgłoszenia do wypału</h2>
        @forelse ($records->firingRequests as $batch)
            <div class="{{ $card }} mb-2.5 flex flex-wrap items-center gap-3.5">
                <div class="flex-none text-[12.5px] text-hint tabular-nums">{{ $batch->created_at->format('j.m.Y') }}</div>
                <div class="min-w-0 flex-[1_1_260px] text-[13.5px] text-ink">{{ collect($batch->summary())->map(fn ($value, $label) => $label.': '.$value)->join(' · ') }}</div>
                <span class="flex-none rounded-full px-3 py-[5px] text-[11.5px] {{ $batch->status->chipClass() }}">{{ $batch->status->label() }}</span>
                <a href="{{ route('admin.firing.requests.index') }}#zgloszenie-{{ $batch->id }}" class="inline-flex min-h-11 flex-none items-center rounded-full border border-line-strong px-4 text-[12.5px] text-ink hover:border-ink hover:bg-sand-dark hover:text-ink">Szczegóły</a>
            </div>
        @empty
            <p class="{{ $empty }}">Żadnego wsadu.</p>
        @endforelse
    </section>

    @if ($records->stockNotifications->isNotEmpty())
        <section aria-labelledby="czeka" class="mb-10">
            <h2 id="czeka" class="{{ $heading }}">Czeka na powrót</h2>
            @foreach ($records->stockNotifications as $notification)
                <div class="{{ $card }} mb-2.5 text-[13.5px]">
                    <span class="text-hint tabular-nums">{{ $notification->created_at->format('j.m.Y') }}</span> · {{ $notification->variant?->product?->name }}{{ $notification->variant?->label ? ' ('.$notification->variant->label.')' : '' }} · {{ $notification->locale === 'pl' ? 'mail po polsku' : 'mail po angielsku' }}
                </div>
            @endforeach
        </section>
    @endif

    </x-admin::tab-panel>

    @if ($hasCases)
      <x-admin::tab-panel name="sprawy">
        <section aria-labelledby="sprawy" class="mb-10">
            <h2 id="sprawy" class="sr-only">Odstąpienia i reklamacje</h2>
            @foreach ($records->withdrawals as $withdrawal)
                <div class="{{ $card }} mb-2.5 text-[13.5px]">
                    <span class="text-hint tabular-nums">{{ $withdrawal->submitted_at->format('j.m.Y') }}</span> · Odstąpienie od umowy {{ $withdrawal->order_number }} · {{ $withdrawal->handled_at ? 'załatwione' : 'czeka na zwrot' }}
                </div>
            @endforeach
            @foreach ($records->receivedComplaints as $complaint)
                <div class="{{ $card }} mb-2.5 text-[13.5px]">
                    <span class="text-hint tabular-nums">{{ $complaint->received_on->format('j.m.Y') }}</span> · Reklamacja{{ $complaint->order_number ? ' '.$complaint->order_number : '' }} · {{ $complaint->status === ComplaintStatus::Open ? $complaint->deadlineLabel() : 'odpowiedziana' }}
                    <p class="mt-1 text-label [overflow-wrap:anywhere]">{{ Str::limit($complaint->description, 200) }}</p>
                </div>
            @endforeach
            @foreach ($records->complaints->whereNull('complaint_id') as $complaint)
                <div class="{{ $card }} mb-2.5 text-[13.5px]">
                    <span class="text-hint tabular-nums">{{ $complaint->received_on->format('j.m.Y') }}</span> · Reklamacja{{ $complaint->order_number ? ' '.$complaint->order_number : '' }}
                </div>
            @endforeach
        </section>
      </x-admin::tab-panel>
    @endif

    <x-admin::tab-panel name="rodo">
    <section aria-labelledby="usuniecie" class="mb-10 rounded-[4px] border border-line bg-cream px-[18px] py-5 md:px-[26px] md:py-7">
        <h2 id="usuniecie" class="mb-2 font-serif text-[24px]">Usuń jej dane</h2>
        <p class="mb-4 max-w-[64ch] text-[13.5px] text-graphite">
            Na jej prośbę (RODO, art. 17). Znikną e-mail, telefon, adres, Paczkomat, notatki, napisy na kubkach, dedykacje, zdjęcia z pomysłów
            i niewysłane do niej maile. <strong class="font-normal text-ink">Opłacone zamówienia zostają</strong> z numerem, kwotą, imieniem i nazwiskiem oraz NIP-em —
            księgowość musi je trzymać przez 5 lat. Odstąpienia i reklamacje zostają bez adresu e-mail, na wypadek sporu. Tego nie da się cofnąć.
        </p>

        @if ($blockers !== [])
            <div role="alert" class="rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">
                <p class="mb-2">Najpierw dokończ te sprawy — do nich potrzebny jest jeszcze jej adres:</p>
                <ul class="grid gap-1 pl-5 [list-style:disc]">
                    @foreach ($blockers as $blocker)
                        <li><a href="{{ $blocker['url'] }}" class="text-alert-text underline">{{ $blocker['label'] }}</a></li>
                    @endforeach
                </ul>
            </div>
        @else
            <form method="post" action="{{ route('admin.customers.anonymize', $customer->key) }}" class="grid max-w-[460px] gap-3">
                @csrf
                @method('delete')
                <label for="confirm_email" class="grid gap-1.5 text-[12.5px] text-label">
                    Żeby potwierdzić, wpisz jej adres e-mail
                    <input id="confirm_email" name="confirm_email" type="email" autocomplete="off" required
                           @error('confirm_email') aria-invalid="true" aria-describedby="confirm_email-error" @enderror
                           class="min-w-0 rounded-[4px] border border-line bg-white px-3 py-2.5 text-[14px] text-ink focus:border-ink">
                </label>
                @error('confirm_email')
                    <p id="confirm_email-error" class="text-[12.5px] text-error">{{ $message }}</p>
                @enderror
                <button class="min-h-11 rounded-full bg-error px-6 text-[13.5px] text-linen hover:bg-ink">Usuń dane na zawsze</button>
            </form>
        @endif
    </section>

    @if ($records->privacyRequests->isNotEmpty())
        <section aria-labelledby="historia-rodo">
            <h2 id="historia-rodo" class="{{ $heading }}">Jej żądania RODO</h2>
            @foreach ($records->privacyRequests as $privacyRequest)
                <div class="mb-2 flex flex-wrap items-center gap-x-4 gap-y-1.5 rounded-[4px] border border-divider bg-linen px-4 py-3 text-[13px]">
                    <span class="flex-none text-hint tabular-nums">{{ $privacyRequest->created_at->format('j.m.Y, H:i') }}</span>
                    <span class="flex-none rounded-full px-3 py-[5px] text-[11.5px] {{ $privacyRequest->type->chipClass() }}">{{ $privacyRequest->type->label() }}</span>
                    <span class="min-w-0 flex-[1_1_240px] text-graphite">{{ $privacyRequest->summaryLabel() }}</span>
                    <span class="flex-none text-hint">{{ $privacyRequest->performed_by }}</span>
                </div>
            @endforeach
        </section>
    @endif
    </x-admin::tab-panel>
  </x-admin::tabs>
</x-admin::layout>
