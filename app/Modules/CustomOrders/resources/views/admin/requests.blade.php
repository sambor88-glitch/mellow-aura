@use('App\Modules\CustomOrders\Enums\CustomOrderStatus')
@use('App\Modules\Shared\Support\Money')
@php
    $filter = 'rounded-full border px-[18px] py-[9px] text-[13px]';
    $filterOn = 'border-ink bg-ink text-linen hover:text-linen';
    $filterOff = 'border-line-strong text-lead hover:border-ink hover:bg-sand hover:text-ink';
    $field = 'min-w-0 rounded-[4px] border border-line bg-white px-3 py-2 text-[13.5px] text-ink focus:border-ink';
    $bag = $errors->getBag('pomysl');
@endphp
<x-admin::layout title="Zamówienia indywidualne" lead="Pomysły z formularza na stronie zamówień indywidualnych, od najstarszego. Każdy przyszedł też do Ciebie mailem ze zdjęciami — na maila odpowiadasz klientce, tutaj zapisujesz wycenę i notatkę dla siebie.">
    <nav aria-label="Status" class="mb-6 flex flex-wrap gap-2">
        <a href="{{ route('admin.custom-orders.index') }}" @if ($current === null) aria-current="page" @endif class="{{ $filter }} {{ $current === null ? $filterOn : $filterOff }}">Do zrobienia ({{ $openCount }})</a>
        @foreach (CustomOrderStatus::cases() as $status)
            <a href="{{ route('admin.custom-orders.index', ['status' => $status->value]) }}" @if ($current === $status) aria-current="page" @endif class="{{ $filter }} {{ $current === $status ? $filterOn : $filterOff }}">{{ $status->label() }}@unless ($status === CustomOrderStatus::Shipped) ({{ (int) ($counts[$status->value] ?? 0) }})@endunless</a>
        @endforeach
    </nav>

    @if ($bag->any())
        <p role="alert" class="mb-4 rounded-[4px] border border-alert-line bg-alert px-4 py-3 text-[13.5px] text-alert-text">{{ $bag->first() }}</p>
    @endif

    @if ($requests->isEmpty())
        <div class="rounded-[4px] border border-dashed border-line-strong bg-linen px-7 py-10 text-center">
            <div class="mb-2 font-serif text-[22px]">{{ $current ? 'Nic w „'.$current->label().'”' : 'Nie ma pomysłów do zrobienia' }}</div>
            <p class="mx-auto max-w-[44ch] text-[13.5px] text-label">
                {{ $current ? 'Gdy zmienisz status pomysłu na „'.$current->label().'”, znajdziesz go tutaj.' : 'Gdy ktoś wyśle formularz „Wyślij pomysł”, zgłoszenie pojawi się tutaj razem ze zdjęciami.' }}
            </p>
        </div>
    @else
        <div class="grid gap-2.5">
            @foreach ($requests as $idea)
                @php($photos = $idea->getMedia('photos'))
                <article id="pomysl-{{ $idea->id }}" class="grid scroll-mt-6 gap-y-3 rounded-[4px] border border-line bg-cream px-[18px] py-4">
                    <div class="flex flex-wrap items-start gap-x-5 gap-y-3">
                        <div class="flex-none text-[12.5px] text-hint tabular-nums">
                            <div>{{ $idea->created_at->format('j.m.Y') }}</div>
                            <div>{{ $idea->created_at->format('H:i') }}</div>
                        </div>
                        <div class="min-w-0 flex-[1_1_260px]">
                            <h2 class="text-[14.5px] text-ink">
                                {{ $idea->name }}
                                <span class="text-[12.5px] text-label [overflow-wrap:anywhere]">· <a href="mailto:{{ $idea->email }}">{{ $idea->email }}</a>{{ $idea->phone ? ' · '.$idea->phone : '' }}</span>
                            </h2>
                            <div class="mt-1 text-[13px] text-lead tabular-nums">{{ collect([
                                $idea->kind->label(),
                                $idea->quantity ? 'ile: '.$idea->quantity : null,
                                $idea->needed_by ? 'na kiedy: '.$idea->needed_by : null,
                                $idea->budget ? 'budżet: '.$idea->budget : null,
                                $idea->quote !== null ? 'wycena '.Money::format($idea->quote) : null,
                            ])->filter()->join(' · ') }}</div>
                            <p class="mt-1.5 rounded-[4px] bg-linen px-3 py-2 text-[13px] whitespace-pre-line [overflow-wrap:anywhere] text-graphite">{{ $idea->description }}</p>
                        </div>
                        <span class="flex-none rounded-full px-3 py-[5px] text-[11.5px] {{ $idea->status->chipClass() }}">{{ $idea->status->label() }}</span>
                    </div>

                    @if ($photos->isNotEmpty())
                        <ul aria-label="Zdjęcia od {{ $idea->name }}" class="flex flex-wrap gap-2">
                            @foreach ($photos as $photo)
                                <li>
                                    <a href="{{ route('admin.custom-orders.photo', [$idea, $photo->id]) }}" target="_blank" rel="noopener" class="block overflow-hidden rounded-[4px] border border-line hover:border-ink">
                                        <img src="{{ route('admin.custom-orders.photo', [$idea, $photo->id]) }}" alt="Zdjęcie {{ $loop->iteration }} od {{ $idea->name }}" loading="lazy" decoding="async" class="size-24 object-cover">
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <form method="post" action="{{ route('admin.custom-orders.update', $idea) }}" class="flex flex-wrap items-end gap-2 border-t border-divider pt-3">
                        @csrf
                        @method('patch')
                        <div class="min-w-0">
                            <label for="pomysl-{{ $idea->id }}-status" class="mb-1 block text-[12px] text-label">Status</label>
                            <select id="pomysl-{{ $idea->id }}-status" name="status" class="{{ $field }}">
                                @foreach (CustomOrderStatus::cases() as $status)
                                    <option value="{{ $status->value }}" @selected($idea->status === $status)>{{ $status->label() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="min-w-0">
                            <label for="pomysl-{{ $idea->id }}-quote" class="mb-1 block text-[12px] text-label">Wycena (zł)</label>
                            <input id="pomysl-{{ $idea->id }}-quote" name="quote" type="text" inputmode="decimal" autocomplete="off" placeholder="np. 450"
                                   value="{{ $idea->quote !== null ? Money::input($idea->quote) : '' }}" class="{{ $field }} w-28 tabular-nums">
                        </div>
                        <div class="min-w-0 flex-[1_1_260px]">
                            <label for="pomysl-{{ $idea->id }}-notes" class="mb-1 block text-[12px] text-label">Notatka — widzisz ją tylko Ty</label>
                            <textarea id="pomysl-{{ $idea->id }}-notes" name="notes" rows="2" maxlength="5000" class="{{ $field }} block w-full">{{ $idea->notes }}</textarea>
                        </div>
                        <button class="min-h-10 rounded-full border border-line-strong px-4 py-2 text-[12.5px] text-ink transition duration-300 hover:border-ink hover:bg-sand-dark">Zapisz</button>
                    </form>
                </article>
            @endforeach
        </div>
    @endif
</x-admin::layout>
