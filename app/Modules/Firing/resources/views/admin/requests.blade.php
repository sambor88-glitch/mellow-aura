@use('App\Modules\Firing\Enums\FiringRequestStatus')
@use('App\Modules\Shared\Support\Money')
@php
    $filter = 'rounded-full border px-[18px] py-[9px] text-[13px]';
    $filterOn = 'border-ink bg-ink text-linen hover:text-linen';
    $filterOff = 'border-line-strong text-lead hover:border-ink hover:bg-sand hover:text-ink';
    $select = 'min-w-0 rounded-[4px] border border-line bg-white px-3 py-2 text-[13.5px] text-ink focus:border-ink';
    $bag = $errors->getBag('zgloszenie');
@endphp
<x-admin::layout title="Zgłoszenia do wypału" lead="Wsady z formularza na stronie wypałów, pogrupowane w partie według dnia, w którym klienci je przywożą. Każde zgłoszenie przyszło też do Ciebie mailem.">
    <nav aria-label="Widok" class="mb-6 flex flex-wrap gap-2">
        <a href="{{ route('admin.firing.requests.index') }}" @unless ($collected) aria-current="page" @endunless class="{{ $filter }} {{ $collected ? $filterOff : $filterOn }}">Do zrobienia ({{ $openCount }})</a>
        <a href="{{ route('admin.firing.requests.index', ['widok' => 'odebrane']) }}" @if ($collected) aria-current="page" @endif class="{{ $filter }} {{ $collected ? $filterOn : $filterOff }}">Odebrane</a>
        <a href="{{ route('admin.firing.edit') }}" class="{{ $filter }} {{ $filterOff }}">Cennik i dzień wsadu</a>
    </nav>

    @if ($bag->any())
        <p role="alert" class="mb-4 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">{{ $bag->first() }}</p>
    @endif

    @if ($requests->isEmpty())
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">{{ $collected ? 'Nikt jeszcze nie odebrał prac' : 'Nie ma wsadów do zrobienia' }}</div>
            <p class="mx-auto max-w-[44ch] text-[13.5px] text-label">
                {{ $collected ? 'Gdy oznaczysz zgłoszenie jako „Odebrane”, znajdziesz je tutaj.' : 'Gdy ktoś wyśle formularz „Zgłoś wsad”, zgłoszenie pojawi się tutaj w partii z dniem, który wybrał.' }}
            </p>
        </div>
    @else
        @php
            // On the „Odebrane” tab the list is one group without a batch form.
            $groups = $collected ? collect([['date' => null, 'label' => null, 'requests' => $requests]]) : $loads;
        @endphp
        <div class="grid gap-8">
            @foreach ($groups as $load)
                <section @if ($load['date']) id="partia-{{ $load['date'] }}" @endif class="scroll-mt-6">
                    @if ($load['label'])
                        <div class="mb-3 flex flex-wrap items-end justify-between gap-x-5 gap-y-2.5 border-b border-divider pb-3">
                            <div>
                                <h2 class="font-serif text-[22px] leading-tight">{{ $load['date'] ? 'Partia: '.$load['label'] : $load['label'] }}</h2>
                                <p class="mt-1 text-[13px] text-label tabular-nums">{{ collect([
                                    trans_choice('{1} :count zgłoszenie|[2,4] :count zgłoszenia|[5,*] :count zgłoszeń', $load['requests']->count()),
                                    $load['kilograms'] ? $load['kilograms'].' kg' : null,
                                    'z cennika '.Money::format($load['quote']),
                                    $load['unpriced'] ? $load['unpriced'].' do wyceny' : null,
                                ])->filter()->join(' · ') }}</p>
                                @unless ($load['date'])
                                    <p class="mt-1 text-[13px] text-label">Wpisz dzień przy zgłoszeniu, a trafi do partii.</p>
                                @endunless
                            </div>
                            @if ($load['date'])
                                <form method="post" action="{{ route('admin.firing.requests.update-load', $load['date']) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    @method('patch')
                                    <label for="partia-{{ $load['date'] }}-status" class="text-[13px] text-graphite">Cała partia</label>
                                    <select id="partia-{{ $load['date'] }}-status" name="status" class="{{ $select }}">
                                        @foreach (FiringRequestStatus::cases() as $status)
                                            <option value="{{ $status->value }}" @selected($status === FiringRequestStatus::InKiln)>{{ $status->label() }}</option>
                                        @endforeach
                                    </select>
                                    <button class="min-h-10 rounded-full bg-ink px-4 py-2 text-[12.5px] text-linen transition duration-300 hover:bg-rose hover:text-ink active:scale-[.97]">Zmień całą partię</button>
                                </form>
                            @endif
                        </div>
                    @endif

                    <div class="grid gap-2.5">
                        @foreach ($load['requests'] as $batch)
                            <article id="zgloszenie-{{ $batch->id }}" class="flex scroll-mt-6 flex-wrap items-start gap-x-5 gap-y-3 rounded-[4px] border border-line bg-cream px-[18px] py-4">
                                <div class="flex-none text-[12.5px] text-hint tabular-nums">
                                    <div>{{ $batch->created_at->format('j.m.Y') }}</div>
                                    <div>{{ $batch->created_at->format('H:i') }}</div>
                                </div>
                                <div class="min-w-0 flex-[1_1_260px]">
                                    <h3 class="text-[14.5px] text-ink">
                                        {{ $batch->name }}
                                        <span class="text-[12.5px] text-label [overflow-wrap:anywhere]">· <a href="mailto:{{ $batch->email }}">{{ $batch->email }}</a>{{ $batch->phone ? ' · '.$batch->phone : '' }}</span>
                                    </h3>
                                    <div class="mt-1 text-[13px] text-lead tabular-nums">{{ collect([
                                        $batch->service_label ?? 'Nie wie, jaki wypał — doradź',
                                        $batch->amount(),
                                        $batch->quote !== null ? Money::format($batch->quote) : 'do wyceny',
                                    ])->filter()->join(' · ') }}</div>
                                    @if ($batch->message)
                                        <p class="mt-1.5 rounded-[4px] bg-linen px-3 py-2 text-[13px] whitespace-pre-line [overflow-wrap:anywhere] text-graphite">{{ $batch->message }}</p>
                                    @endif
                                </div>
                                <div class="flex flex-none flex-col items-start gap-2.5 sm:items-end">
                                    <span class="rounded-full px-3 py-[5px] text-[11.5px] {{ $batch->status->chipClass() }}">{{ $batch->status->label() }}</span>
                                    <form method="post" action="{{ route('admin.firing.requests.update', $batch) }}" class="flex flex-wrap items-end gap-2">
                                        @csrf
                                        @method('patch')
                                        <div class="min-w-0">
                                            <label for="zgloszenie-{{ $batch->id }}-status" class="mb-1 block text-[12px] text-label">Status</label>
                                            <select id="zgloszenie-{{ $batch->id }}-status" name="status" class="{{ $select }}">
                                                @foreach (FiringRequestStatus::cases() as $status)
                                                    <option value="{{ $status->value }}" @selected($batch->status === $status)>{{ $status->label() }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="min-w-0">
                                            <label for="zgloszenie-{{ $batch->id }}-day" class="mb-1 block text-[12px] text-label">Dzień przywiezienia</label>
                                            <input id="zgloszenie-{{ $batch->id }}-day" name="drop_off_on" type="date" value="{{ $batch->drop_off_on?->toDateString() }}"
                                                   @if ($loadDays->isNotEmpty()) list="dni-wsadu" @endif class="{{ $select }} tabular-nums">
                                        </div>
                                        <button class="min-h-10 rounded-full border border-line-strong px-4 py-2 text-[12.5px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark">Zapisz</button>
                                    </form>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>
        @if ($loadDays->isNotEmpty())
            <datalist id="dni-wsadu">
                @foreach ($loadDays as $date => $label)
                    <option value="{{ $date }}">{{ $label }}</option>
                @endforeach
            </datalist>
        @endif
    @endif
</x-admin::layout>
