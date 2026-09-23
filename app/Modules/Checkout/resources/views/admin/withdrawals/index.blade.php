<x-admin::layout title="Odstąpienia od umowy" lead="Oświadczenia z formularza „Odstąp od umowy tutaj”. Na zwrot pieniędzy masz 14 dni od dnia, w którym przyszło oświadczenie.">
    @if ($withdrawals->isEmpty())
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">Jeszcze nic tu nie ma</div>
            <p class="mx-auto max-w-[44ch] text-[13.5px] text-label">Gdy ktoś odstąpi od umowy przez formularz na stronie, oświadczenie pojawi się tutaj razem z datą, godziną i zamówieniem.</p>
        </div>
    @else
        <div class="grid gap-2.5">
            @foreach ($withdrawals as $withdrawal)
                @php
                    $refundBy = $withdrawal->submitted_at->copy()->addDays(14);
                @endphp
                <article @class([
                    'flex flex-wrap items-start gap-x-5 gap-y-3 rounded-[4px] border px-[18px] py-4',
                    'border-line bg-cream' => $withdrawal->handled_at === null,
                    'border-divider bg-linen' => $withdrawal->handled_at !== null,
                ])>
                    <div class="flex-none text-[12.5px] text-hint tabular-nums">
                        <div>{{ $withdrawal->submitted_at->format('j.m.Y') }}</div>
                        <div>{{ $withdrawal->submitted_at->format('H:i') }}</div>
                    </div>
                    <div class="min-w-0 flex-[1_1_260px]">
                        <h2 class="text-[14.5px] text-ink">{{ $withdrawal->name }} <span class="text-[12.5px] text-label [overflow-wrap:anywhere]">· {{ $withdrawal->email }}</span></h2>
                        <div class="mt-1 text-[13px] text-lead">
                            @if ($withdrawal->order)
                                <a href="{{ route('admin.orders.show', $withdrawal->order) }}">{{ $withdrawal->order_number }}</a>
                            @else
                                {{ $withdrawal->order_number }} <span class="text-error">— nie ma takiego zamówienia z tym adresem, sprawdź ręcznie</span>
                            @endif
                            · {{ $withdrawal->scope->label() }}
                        </div>
                        @if ($withdrawal->items !== null)
                            <p class="mt-1.5 rounded-[4px] bg-linen px-3 py-2 text-[13px] whitespace-pre-line [overflow-wrap:anywhere] text-graphite">{{ $withdrawal->items }}</p>
                        @endif
                    </div>
                    <div class="flex flex-none flex-wrap items-center gap-2.5">
                        @if ($withdrawal->handled_at === null)
                            <span class="rounded-full bg-alert px-3 py-[5px] text-[11.5px] text-error">Zwrot do {{ $refundBy->format('j.m') }}</span>
                        @else
                            <span class="rounded-full bg-sand-dark px-3 py-[5px] text-[11.5px] text-label">Załatwione {{ $withdrawal->handled_at->format('j.m') }}</span>
                        @endif
                        <form method="post" action="{{ route('admin.withdrawals.update', $withdrawal) }}">
                            @csrf
                            @method('patch')
                            <button class="rounded-full border border-line-strong px-4 py-2 text-[12.5px] text-ink hover:border-ink hover:bg-sand-dark">{{ $withdrawal->handled_at === null ? 'Oznacz jako załatwione' : 'Przywróć do załatwienia' }}</button>
                        </form>
                    </div>
                </article>
            @endforeach
        </div>

        @if ($withdrawals->hasPages())
            <div class="mt-6 flex justify-between gap-4 text-[13.5px]">
                @if ($withdrawals->previousPageUrl())
                    <a href="{{ $withdrawals->previousPageUrl() }}">← Nowsze</a>
                @else
                    <span></span>
                @endif
                @if ($withdrawals->nextPageUrl())
                    <a href="{{ $withdrawals->nextPageUrl() }}">Starsze →</a>
                @endif
            </div>
        @endif
    @endif
</x-admin::layout>
