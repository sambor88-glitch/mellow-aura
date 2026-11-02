{{-- „Podsumowanie sprzedaży” (MA-344): four numbers, revenue bar by bar, what sold best and what sells apart.
     Every currency on its own — złoty and euro are never added up. --}}
@php
    use App\Modules\Checkout\Support\SalesSummary;
    use App\Modules\Shared\Support\Money;

    $last = $to->subSecond();
    $dates = $from->isSameDay($last)
        ? $from->locale('pl')->translatedFormat('j F Y')
        : $from->locale('pl')->translatedFormat($from->year === $last->year ? 'j F' : 'j F Y').' – '.$last->locale('pl')->translatedFormat('j F Y');
    $currencyNames = ['PLN' => 'W złotych', 'EUR' => 'W euro'];
@endphp
<x-admin::layout title="Podsumowanie sprzedaży" lead="Ile przyszło, ile zamówień i co sprzedaje się najlepiej. Liczą się zamówienia opłacone w wybranym okresie.">
    <nav aria-label="Okres" class="mb-3 flex flex-wrap gap-2">
        @foreach ($periods as $option)
            <a href="{{ route('admin.sales.index', ['period' => $option->value]) }}" @if ($option === $period) aria-current="page" @endif @class([
                'inline-flex min-h-11 items-center rounded-full border px-4 text-[13px]',
                'border-ink bg-ink text-linen hover:text-linen' => $option === $period,
                'border-line-strong text-lead hover:border-ink hover:bg-sand hover:text-ink' => $option !== $period,
            ])>{{ $option->label() }}</a>
        @endforeach
    </nav>
    <p class="mb-7 text-[12.5px] text-hint max-md:mb-5">{{ ucfirst($dates) }}. Strzałki pod liczbami porównują z: {{ $period->previousLabel() }}.</p>

    @if ($currencies === [])
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">W tym okresie nic się jeszcze nie sprzedało</div>
            <p class="mx-auto mb-5 max-w-[44ch] text-[13.5px] text-label">Pierwsze opłacone zamówienie pojawi się tutaj razem z kwotą, produktami i wykresem dzień po dniu.</p>
            <a href="{{ route('admin.orders.index') }}" class="inline-flex min-h-11 items-center rounded-full bg-ink px-[26px] text-[13.5px] text-linen hover:bg-navy hover:text-linen">Zobacz zamówienia</a>
        </div>
    @endif

    @foreach ($currencies as $section)
        @php
            $now = $section['current'];
            $before = $section['previous'];
            $money = fn (int $amount) => Money::format($amount, $section['currency']);
            $tiles = [
                ['label' => 'Przychód', 'value' => $money($now['revenue']), 'change' => SalesSummary::change($now['revenue'], $before['revenue']), 'was' => $money($before['revenue']),
                    'hint' => $now['refunds'] > 0 ? 'Wpłaty '.$money($now['paid']).', zwroty −'.$money($now['refunds']) : 'Opłacone zamówienia minus zwroty'],
                ['label' => 'Zamówienia', 'value' => (string) $now['orders'], 'change' => SalesSummary::change($now['orders'], $before['orders']), 'was' => (string) $before['orders'],
                    'hint' => 'Opłacone, także warsztaty i vouchery'],
                ['label' => 'Średni koszyk', 'value' => $money($now['average']), 'change' => SalesSummary::change($now['average'], $before['average']), 'was' => $money($before['average']),
                    'hint' => 'Razem z dostawą'],
                ['label' => 'Wysyłki', 'value' => $money($now['shipping']), 'change' => SalesSummary::change($now['shipping'], $before['shipping']), 'was' => $money($before['shipping']),
                    'hint' => 'Tyle klienci zapłacili za dostawę — jest w przychodzie'],
            ];
            $chart = $section['chart'];
            $top = SalesSummary::axisTop(max(array_column($chart, 'amount')));
            $every = (int) ceil(count($chart) / 6);
            $chartId = 'sales-chart-'.strtolower($section['currency']);
        @endphp

        <section @class(['mb-11 max-md:mb-8']) aria-labelledby="{{ $chartId }}-heading">
            <h2 id="{{ $chartId }}-heading" @class(['mb-3.5 font-serif text-[26px] leading-tight font-light', 'sr-only' => count($currencies) === 1])>{{ $currencyNames[$section['currency']] ?? $section['currency'] }}</h2>

            {{-- The tiles of „Na dziś”: rows of one card on a phone, tiles on a computer. --}}
            <div class="mb-6 overflow-hidden rounded-[4px] border border-line bg-cream md:flex md:flex-wrap md:gap-4 md:overflow-visible md:rounded-none md:border-0 md:bg-transparent">
                @foreach ($tiles as $tile)
                    <div class="border-b border-line px-4 py-3 last:border-b-0 md:flex-[1_1_180px] md:rounded-[4px] md:border md:border-line md:bg-cream md:px-[22px] md:py-5 md:last:border-b">
                        <div class="flex items-baseline justify-between gap-3 md:flex-col md:items-start md:justify-start md:gap-0">
                            <div class="text-[14.5px] text-lead md:order-2 md:mt-1.5 md:text-[11.5px] md:tracking-[0.14em] md:text-label md:uppercase">{{ $tile['label'] }}</div>
                            <div class="font-serif text-[22px] leading-none font-light text-ink tabular-nums md:order-1 md:text-[34px]" data-tile="{{ $tile['label'] }}">{{ $tile['value'] }}</div>
                        </div>
                        <div class="mt-1.5 text-[12.5px] text-hint tabular-nums md:mt-2">
                            <span class="text-ink">{{ $tile['change'] }}</span> · było {{ $tile['was'] }}
                        </div>
                        <div class="mt-0.5 text-[12.5px] text-hint">{{ $tile['hint'] }}</div>
                    </div>
                @endforeach
            </div>

            {{-- One series, so no legend: the heading names it. Each bar answers on hover and on focus; the table under it
                 holds the same numbers without either. --}}
            <div class="mb-6 rounded-[4px] border border-line bg-cream px-[26px] py-7 max-md:px-[18px] max-md:py-5">
                <h3 class="mb-5 text-[11.5px] tracking-[0.14em] text-label uppercase">Przychód {{ $period->byWeek() ? 'tydzień po tygodniu' : 'dzień po dniu' }}</h3>

                @if ($top === 0)
                    <p class="text-[13.5px] text-label">W tym okresie nic jeszcze nie przyszło{{ $before['revenue'] > 0 ? ' — wcześniej było '.$money($before['revenue']) : '' }}.</p>
                @else
                    <div class="flex gap-2">
                        <div aria-hidden="true" class="flex h-[180px] w-[64px] flex-none flex-col justify-between text-right text-[11.5px] text-hint tabular-nums">
                            <span class="-mt-2">{{ $money($top) }}</span>
                            <span>{{ $money(intdiv($top, 2)) }}</span>
                            <span class="-mb-2">0</span>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="relative h-[180px] border-b border-line-strong">
                                <div aria-hidden="true" class="absolute inset-x-0 top-0 border-t border-divider"></div>
                                <div aria-hidden="true" class="absolute inset-x-0 top-1/2 border-t border-divider"></div>
                                <div @class(['relative flex h-full items-end', 'gap-[2px]' => count($chart) <= 40, 'gap-px' => count($chart) > 40]) role="list" aria-label="Przychód {{ $period->byWeek() ? 'w kolejnych tygodniach' : 'w kolejnych dniach' }}">
                                    @foreach ($chart as $index => $bar)
                                        @php
                                            $text = $bar['long'].': '.$money($bar['amount']);
                                            $align = $index < count($chart) / 3 ? 'left-0' : ($index >= count($chart) * 2 / 3 ? 'right-0' : 'left-1/2 -translate-x-1/2');
                                        @endphp
                                        <div role="listitem" tabindex="0" aria-label="{{ $text }}" class="group relative flex h-full min-w-0 flex-1 cursor-default items-end justify-center outline-none">
                                            @if ($bar['amount'] > 0)
                                                <div class="w-full max-w-6 rounded-t-[4px] bg-navy transition-colors group-hover:bg-navy-text group-focus-visible:bg-navy-text" style="height: {{ max(1, round($bar['amount'] * 100 / $top, 2)) }}%"></div>
                                            @endif
                                            <div aria-hidden="true" class="pointer-events-none absolute -top-2 z-10 hidden -translate-y-full rounded-[4px] bg-ink px-2.5 py-1.5 text-[12px] whitespace-nowrap text-linen tabular-nums group-hover:block group-focus-visible:block {{ $align }}">
                                                <strong class="font-normal">{{ $money($bar['amount']) }}</strong> <span class="text-on-dark">{{ $bar['long'] }}</span>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                            {{-- A label at every few bars, the ones in the last third aligned to their bar's right edge, so none sticks out of the card. --}}
                            <div aria-hidden="true" class="relative mt-1.5 h-4 text-[11px] text-hint tabular-nums">
                                @foreach ($chart as $index => $bar)
                                    @continue($index % $every !== 0)
                                    <span class="absolute top-0 whitespace-nowrap" style="{{ $index >= count($chart) * 2 / 3 ? 'right: '.round(100 - ($index + 1) * 100 / count($chart), 2).'%' : 'left: '.round($index * 100 / count($chart), 2).'%' }}">{{ $bar['label'] }}</span>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <details class="mt-5 text-[13px]">
                        <summary class="cursor-pointer text-lead">Pokaż jako tabelę</summary>
                        <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-6 gap-y-1 tabular-nums">
                            @foreach ($chart as $bar)
                                <dt class="text-label">{{ $bar['long'] }}</dt>
                                <dd class="text-ink">{{ $money($bar['amount']) }}</dd>
                            @endforeach
                        </dl>
                    </details>
                @endif
            </div>

            <div class="flex flex-wrap gap-6">
                <div class="min-w-0 flex-[1_1_340px] rounded-[4px] border border-line bg-cream px-[26px] py-7 max-md:px-[18px] max-md:py-5">
                    <h3 class="mb-4 text-[11.5px] tracking-[0.14em] text-label uppercase">Najlepiej się sprzedaje</h3>
                    @if ($now['top'] === [])
                        <p class="text-[13.5px] text-label">W tym okresie żaden produkt ze sklepu nie poszedł. Warsztaty i vouchery liczą się obok.</p>
                    @else
                        <ol class="grid gap-3">
                            @foreach ($now['top'] as $place => $product)
                                <li class="flex items-baseline gap-3">
                                    <span class="w-4 flex-none text-[12.5px] text-hint tabular-nums">{{ $place + 1 }}.</span>
                                    <span class="min-w-0 flex-1 text-[14.5px] text-ink [overflow-wrap:anywhere]">{{ $product['name'] }}</span>
                                    <span class="flex-none text-[12.5px] text-label tabular-nums">{{ $product['pieces'] }} szt.</span>
                                    <span class="w-[92px] flex-none text-right font-serif text-[17px] font-light tabular-nums">{{ $money($product['amount']) }}</span>
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </div>

                <div class="min-w-0 flex-[1_1_260px] rounded-[4px] border border-line bg-cream px-[26px] py-7 max-md:px-[18px] max-md:py-5">
                    <h3 class="mb-4 text-[11.5px] tracking-[0.14em] text-label uppercase">Osobno</h3>
                    <dl class="grid gap-3">
                        @foreach ([
                            ['Warsztaty', $now['workshops'], fn (int $count) => trans_choice('miejsce|miejsca|miejsc', $count, [], 'pl')],
                            ['Vouchery', $now['vouchers'], fn (int $count) => 'szt.'],
                            ['Zamówienia indywidualne', $now['custom'], fn (int $count) => trans_choice('wpłata|wpłaty|wpłat', $count, [], 'pl')],
                        ] as [$label, $row, $unit])
                            <div class="flex items-baseline gap-3">
                                <dt class="min-w-0 flex-1 text-[14.5px] text-ink">{{ $label }} <span class="text-[12.5px] text-label tabular-nums">· {{ $row['count'] }} {{ $unit($row['count']) }}</span></dt>
                                <dd class="flex-none font-serif text-[17px] font-light tabular-nums">{{ $money($row['amount']) }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    <p class="mt-4 text-[12.5px] text-hint">Są w przychodzie i liczbie zamówień, ale nie na liście produktów.</p>
                </div>
            </div>
        </section>
    @endforeach

    @if ($currencies !== [])
        <p class="max-w-[70ch] text-[12.5px] text-hint">Zwroty to brakujące sztuki, które ktoś kupił chwilę wcześniej, i odwołane albo przepełnione warsztaty. Zwrotów po odstąpieniu od umowy i po reklamacji panel jeszcze nie zna — nie są odjęte.</p>
    @endif
</x-admin::layout>
