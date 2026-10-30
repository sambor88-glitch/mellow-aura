@php
    // On the buyer's order: the cards it bought. On the order of a chosen mug: the card it came from (MA-235).
    $giftChoices = \App\Modules\Gifts\Models\GiftChoice::query()
        ->issued()
        ->where(fn ($query) => $query->whereIn('order_item_id', $order->items()->select('id'))->orWhere('fulfilment_order_id', $order->id))
        ->with(['orderItem.order', 'fulfilmentOrder'])
        ->orderBy('id')
        ->get();
@endphp
@if ($giftChoices->isNotEmpty())
    <section class="{{ $card }}">
        <h2 class="mb-2 font-serif text-[23px]">Karty wyboru</h2>
        @foreach ($giftChoices as $giftChoice)
            @php($status = $giftChoice->status())
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-sand-dark py-3 last:border-b-0">
                <div class="min-w-0">
                    <div class="font-serif text-[18px] tracking-[0.06em] select-all">{{ $giftChoice->code }}</div>
                    <div class="mt-0.5 text-[13px] text-label">
                        @if ($giftChoice->fulfilment_order_id === $order->id)
                            Kubek z tej karty — kupiony w zamówieniu <a href="{{ route('admin.orders.show', $giftChoice->orderItem->order) }}">{{ $giftChoice->orderItem->order->number }}</a>
                        @else
                            {{ collect([$giftChoice->size_name, $giftChoice->recipient_name ? 'dla: '.$giftChoice->recipient_name : null, 'wybór do '.$giftChoice->choose_by?->format('j.m.Y')])->filter()->join(' · ') }}
                        @endif
                    </div>
                </div>
                <span class="rounded-full px-3 py-[5px] text-[11.5px] {{ $status->chipClass() }}">{{ $status->label() }}</span>
                @if ($giftChoice->fulfilmentOrder && $giftChoice->fulfilment_order_id !== $order->id)
                    <a href="{{ route('admin.orders.show', $giftChoice->fulfilmentOrder) }}" class="text-[13px]">Zamówienie z wyborem {{ $giftChoice->fulfilmentOrder->number }}</a>
                @endif
            </div>
        @endforeach
        @if (\App\Modules\Admin\Support\PanelAccess::allows(auth()->user(), 'admin.gift-choices.index'))
            <a href="{{ route('admin.gift-choices.index', ['pokaz' => 'wszystkie']) }}" class="mt-3 inline-block text-[13px]">Wszystkie karty wyboru</a>
        @endif
    </section>
@endif
