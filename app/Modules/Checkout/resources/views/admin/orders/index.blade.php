@use('App\Modules\Checkout\Enums\PaymentStatus')
@use('App\Modules\Shared\Support\Money')
@php
    $filter = 'rounded-full border px-[18px] py-[9px] text-[13px]';
    $filterOn = 'border-ink bg-ink text-linen hover:text-linen';
    $filterOff = 'border-line-strong text-lead hover:border-ink hover:bg-sand hover:text-ink';
@endphp
<x-admin::layout title="Zamówienia">
    <div class="mb-[30px] flex flex-wrap gap-4">
        @foreach ($tiles as $tile)
            <div class="min-w-0 flex-[1_1_180px] rounded-[4px] border border-line bg-cream px-[22px] py-5">
                <div class="font-serif text-[34px] leading-none tabular-nums">{{ $tile['value'] }}</div>
                <div class="mt-1.5 text-[11.5px] tracking-[0.14em] text-label uppercase">{{ $tile['label'] }}</div>
                <div class="mt-2 text-[12.5px] text-hint">{{ $tile['hint'] }}</div>
            </div>
        @endforeach
    </div>

    <nav aria-label="Filtry zamówień" class="mb-4 flex flex-wrap gap-2.5">
        <a href="{{ route('admin.orders.index') }}" @if (! $unpaid && ! $status) aria-current="page" @endif class="{{ $filter }} {{ $unpaid || $status ? $filterOff : $filterOn }}">Opłacone</a>
        @foreach ($filters as $option)
            <a href="{{ route('admin.orders.index', ['status' => $option['slug']]) }}" @if ($option['active']) aria-current="page" @endif class="{{ $filter }} {{ $option['active'] ? $filterOn : $filterOff }}">{{ $option['label'] }} ({{ $option['count'] }})</a>
        @endforeach
        <a href="{{ route('admin.orders.index', ['platnosc' => 'nieoplacone']) }}" @if ($unpaid) aria-current="page" @endif class="{{ $filter }} {{ $unpaid ? $filterOn : $filterOff }}">Nieopłacone ({{ $unpaidCount }})</a>
    </nav>

    @if ($orders->isEmpty())
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">Jeszcze nic tu nie ma</div>
            <p class="mx-auto max-w-[42ch] text-[13.5px] text-label">
                {{ match (true) {
                    $unpaid => 'Nie ma zamówień, które czekają na płatność albo się nie udały.',
                    $status !== null => 'Żadne opłacone zamówienie nie ma teraz tego statusu.',
                    default => 'Pierwsze opłacone zamówienie pojawi się tutaj razem z adresem do wysyłki i sposobem płatności.',
                } }}
            </p>
        </div>
    @else
        <div class="grid gap-2.5">
            @foreach ($orders as $order)
                @php
                    [$chip, $chipClass] = match (true) {
                        $order->payment_status === PaymentStatus::Failed => ['Nieudana płatność', 'bg-alert text-error'],
                        $order->payment_status === PaymentStatus::Pending => ['Czeka na płatność', 'bg-linen text-label'],
                        $order->withdrawals->whereNull('handled_at')->isNotEmpty() => ['Odstąpienie od umowy', 'bg-alert text-error'],
                        $order->items->contains(fn ($item) => $item->missing_quantity > 0) => ['Problem: brak sztuki', 'bg-alert text-error'],
                        default => [$order->status->label(), $order->status->chipClass()],
                    };
                @endphp
                <div class="flex flex-wrap items-center gap-3.5 rounded-[4px] border border-line bg-cream px-[18px] py-4">
                    <div class="flex-none text-[12.5px] text-hint tabular-nums">
                        <div>{{ $order->number }}</div>
                        <div>{{ ($order->paid_at ?? $order->created_at)->format('j.m, H:i') }}</div>
                    </div>
                    <div class="min-w-0 flex-[1_1_200px]">
                        <div class="text-[14.5px] text-ink">{{ $order->name }}</div>
                        <div class="mt-0.5 text-[12.5px] text-label">{{ $order->items->map(fn ($item) => $item->product_name.($item->variant_label === '' ? '' : ' ('.$item->variant_label.')').' × '.$item->quantity)->join(', ') }}</div>
                    </div>
                    <div class="flex-none font-serif text-[19px] tabular-nums">{{ Money::format($order->total_gross) }}</div>
                    <span class="flex-none rounded-full px-3 py-[5px] text-[11.5px] {{ $chipClass }}">{{ $chip }}</span>
                    <a href="{{ route('admin.orders.show', $order) }}" class="flex-none rounded-full border border-line-strong px-4 py-2 text-[12.5px] text-ink hover:border-ink hover:bg-sand-dark hover:text-ink">Szczegóły</a>
                </div>
            @endforeach
        </div>

        @if ($orders->hasPages())
            <div class="mt-6 flex justify-between gap-4 text-[13.5px]">
                @if ($orders->previousPageUrl())
                    <a href="{{ $orders->previousPageUrl() }}">← Nowsze</a>
                @else
                    <span></span>
                @endif
                @if ($orders->nextPageUrl())
                    <a href="{{ $orders->nextPageUrl() }}">Starsze →</a>
                @endif
            </div>
        @endif
    @endif
</x-admin::layout>
