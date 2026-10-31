@php
    $t = fn (string $key, array $replace = []) => __('consent::consent.'.$key, $replace);
    $row = 'rounded-[20px] border border-divider bg-cream px-6 py-5';
    $radio = 'group flex cursor-pointer items-start gap-3.5 rounded-[20px] border border-line bg-white px-[18px] py-4 has-checked:border-ink has-checked:bg-sand-dark has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-navy';
@endphp

<x-shared::layout title="{{ $t('title') }}" :noindex="true">
    <div class="mx-auto max-w-[900px] animate-ma-view px-7 pt-14 pb-24">
        <div class="mb-5 text-[10.5px] tracking-[0.3em] text-brown uppercase">{{ $t('eyebrow') }}</div>
        <h1 class="mb-6 font-serif text-[length:clamp(38px,5.2vw,64px)] leading-[1.04] font-light tracking-[-0.02em]">{{ $t('heading') }}</h1>
        <p class="mb-10 max-w-[58ch] text-[17px] leading-[1.7] text-pretty text-lead">
            {{ $t('lead') }}
            {{ $needed ? $t('lead_analytics') : $t('lead_nothing') }}
        </p>

        @if (session('consent_saved'))
            <p role="status" class="mb-6 rounded-[20px] border border-line-strong bg-sand-dark p-4 text-[14px] leading-[1.6] text-graphite">
                {{ $t('saved') }} {{ $analytics ? $t('saved_on') : $t('saved_off') }}
            </p>
        @endif

        <div class="mb-8 grid gap-2.5">
            <div class="{{ $row }}">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4">
                    <h2 class="font-serif text-[21px]">{{ $t('needed') }}</h2>
                    <span class="text-[12.5px] text-label">{{ $t('needed_always') }}</span>
                </div>
                <p class="mt-1 text-[14.5px] leading-[1.6] text-muted">{{ $t('needed_text') }}</p>
            </div>
        </div>

        @if ($needed)
            <form method="post" action="{{ route('consent.store') }}" class="rounded-[20px] border border-divider bg-cream px-[30px] py-8">
                @csrf
                <fieldset>
                    <legend class="mb-3 font-serif text-[21px]">{{ $t('analytics') }}</legend>
                    <p class="mb-4 max-w-[62ch] text-[14.5px] leading-[1.6] text-muted">{{ $t('analytics_text') }}</p>
                    <div class="grid gap-2.5">
                        @foreach (['1' => [$t('accept'), $t('accept_hint')], '0' => [$t('reject'), $t('reject_hint')]] as $value => [$label, $hint])
                            <label class="{{ $radio }}">
                                <input type="radio" name="analytics" value="{{ $value }}" @checked($decided && $analytics === ((string) $value === '1')) required class="sr-only">
                                <span class="mt-1 grid size-4 flex-none place-items-center rounded-full border border-label"><span class="size-2 rounded-full group-has-checked:bg-ink"></span></span>
                                <span class="min-w-0">
                                    <span class="block text-[15px]">{{ $label }}</span>
                                    <span class="mt-0.5 block text-[12.5px] text-label">{{ $hint }}</span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>
                @error('analytics')
                    <p class="mt-2 text-[13px] text-error">{{ $t('choose') }}</p>
                @enderror
                @if ($decidedAt)
                    <p class="mt-3 text-[12.5px] text-hint">{{ $t('decided_at', ['date' => $decidedAt->translatedFormat('j F Y')]) }}</p>
                @endif
                <x-shared::button class="mt-5">{{ $t('save') }}</x-shared::button>
            </form>
        @endif

        <p class="mt-8 text-[14px] text-muted">{!! str_replace(':link', '<a href="'.e(route('content.privacy')).'">'.e($t('all_files_link')).'</a>', e($t('all_files'))) !!}</p>
    </div>
</x-shared::layout>
