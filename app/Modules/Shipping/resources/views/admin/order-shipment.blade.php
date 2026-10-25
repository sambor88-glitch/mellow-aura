@use('App\Modules\Checkout\Enums\OrderStatus')
@use('App\Modules\Shipping\Http\Controllers\Admin\ShipmentController')
@use('App\Modules\Shipping\Models\Shipment')
@use('App\Modules\Shipping\Support\ShipXClient')
@use('App\Modules\Shipping\Support\ShipXStatus')
{{-- „Nadaj w InPost” on the order (MA-132), next to the order's status. Only for a paid parcel that travels with InPost. --}}
@if (ShipmentController::sendable($order))
    @php
        $shipment = Shipment::latestFor($order);
        $locker = $order->shipping_method === 'parcel_locker';
        $row = 'flex flex-wrap justify-between gap-x-6 gap-y-0.5';
    @endphp
    <section class="{{ $card }}" aria-labelledby="shipment-heading">
        <h2 id="shipment-heading" class="mb-3 font-serif text-[23px]">InPost</h2>
        @error('shipment')
            <p role="alert" class="mb-3 rounded-[4px] border border-alert-line bg-alert px-3.5 py-2.5 text-[13.5px] leading-[1.55] text-alert-text">{{ $message }}</p>
        @enderror

        @if (! ShipXClient::configured())
            <p class="text-[13.5px] leading-[1.55] text-label">Nadawanie z panelu włączy się, gdy na serwerze będzie klucz ShipX z InPost Managera. Do tego czasu nadaj paczkę w InPost Managerze, a numer wpisz w „Status”.</p>
        @elseif ($shipment)
            <dl class="grid gap-2 text-[13.5px]">
                <div class="{{ $row }}"><dt class="text-label">W InPost</dt><dd>{{ ShipXStatus::label($shipment->status) }}</dd></div>
                <div class="{{ $row }}"><dt class="text-label">Rozmiar</dt><dd>{{ $shipment->sizeLabel() }}</dd></div>
                @if ($shipment->target_point)
                    <div class="{{ $row }}"><dt class="text-label">Paczkomat</dt><dd class="select-all">{{ $shipment->target_point }}</dd></div>
                @endif
                <div class="{{ $row }}"><dt class="text-label">Nr przesyłki</dt><dd class="tabular-nums select-all [overflow-wrap:anywhere]">{{ $shipment->tracking_number ?? 'pojawi się po pobraniu etykiety' }}</dd></div>
            </dl>
            <a href="{{ route('admin.orders.shipment.label', $order) }}" class="{{ $action }} mt-4 inline-flex min-h-11 items-center justify-center">Pobierz etykietę PDF</a>
            @if ($shipment->tracking_number && in_array($order->status, [OrderStatus::InProgress, OrderStatus::Problem], true))
                <form method="post" action="{{ route('admin.orders.status', $order) }}" class="mt-2.5">
                    @csrf
                    @method('patch')
                    <input type="hidden" name="status" value="{{ OrderStatus::Shipped->value }}">
                    <input type="hidden" name="tracking_number" value="{{ $shipment->tracking_number }}">
                    <button class="w-full rounded-full bg-ink p-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Wysłane — powiadom klientkę</button>
                </form>
                <p class="mt-1.5 text-[12.5px] text-hint">Klientka dostanie maila z numerem przesyłki. Możesz też nic nie klikać — gdy InPost odbierze paczkę, mail pójdzie sam.</p>
            @endif
        @else
            <form method="post" action="{{ route('admin.orders.shipment.store', $order) }}" class="grid gap-3">
                @csrf
                <fieldset>
                    <legend class="mb-1.5 text-[13.5px] text-graphite">Rozmiar paczki</legend>
                    @foreach (Shipment::SIZES as $value => $label)
                        <label class="flex min-h-11 cursor-pointer items-center gap-2.5 text-[14px]">
                            <input type="radio" name="size" value="{{ $value }}" @checked(old('size', 'medium') === $value) class="size-4 accent-ink">
                            {{ $label }}
                        </label>
                    @endforeach
                    @error('size')
                        <p class="mt-1.5 text-[13px] text-error">{{ $message }}</p>
                    @enderror
                </fieldset>
                @if ($locker)
                    <x-shared::field name="target_point" label="Paczkomat klientki" :value="old('target_point', $order->locker_code)"
                                     autocomplete="off" autocapitalize="characters" spellcheck="false"
                                     :hint="$order->locker_code ? 'Wybrany przez klientkę przy zakupie' : 'Klientka nie wybrała — dobierz najbliższy po numerze telefonu'" />
                @else
                    <x-shared::field name="weight" label="Waga w kg" inputmode="decimal" :value="old('weight', '2')" hint="Kurier InPost bierze paczki do 30 kg" />
                @endif
                <button class="w-full rounded-full bg-ink p-3 text-[13.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Nadaj w InPost</button>
            </form>
        @endif
    </section>
@endif
