@php
    $t = fn (string $key, array $replace = []) => __('gifts::choice.page.'.$key, $replace);
    $justChosen = session('gift_choice_chosen') === true;
@endphp
<x-shared::layout :title="$t('title')" :description="$t('description')" :canonical="route('gift-choice.index')" noindex>
    <div class="mx-auto max-w-[640px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-12 pb-24">
        <p class="eyebrow mb-4">{{ $t('eyebrow') }} &middot; {{ $choice->code }}</p>
        @if ($chosen)
            <h1 class="mb-5 font-serif text-[length:clamp(40px,5.2vw,72px)] leading-[.96] font-light tracking-[-0.025em] text-balance"><span class="blur-in">{{ $t('chosen_heading') }}</span></h1>
            @if ($justChosen)
                <p role="status" class="mb-8 max-w-[46ch] text-[16.5px] leading-[1.7] text-pretty text-lead">{{ $t('chosen_lead') }}</p>
            @else
                <p class="mb-8 max-w-[46ch] text-[16.5px] leading-[1.7] text-pretty text-lead">{{ $refusal->message() }}</p>
            @endif
            {{-- What she typed, escaped like everywhere else it shows. --}}
            <dl class="glass grid gap-3 rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)] text-[14.5px]">
                <div>
                    <dt class="text-[12.5px] text-label">{{ $t('chosen_text') }}</dt>
                    <dd class="mt-1 font-serif text-[22px] leading-[1.35] tracking-[0.14em] whitespace-pre-line [overflow-wrap:anywhere]">{{ $chosen->custom_text }}</dd>
                </div>
                <div>
                    <dt class="text-[12.5px] text-label">{{ $t('chosen_glaze') }}</dt>
                    <dd class="mt-1">{{ $chosen->custom_glaze }} &middot; {{ $chosen->variant_label }}</dd>
                </div>
            </dl>
        @else
            <h1 class="mb-5 font-serif text-[length:clamp(40px,5.2vw,72px)] leading-[.96] font-light tracking-[-0.025em] text-balance"><span class="blur-in">{{ $t('closed_heading') }}</span></h1>
            <p class="mb-3 max-w-[46ch] text-[16.5px] leading-[1.7] text-pretty text-lead">{{ $refusal->message() }}</p>
            @if ($contactEmail)
                {{-- The sentence is ours from lang; only the address comes from the panel, so only it is escaped. --}}
                <p class="mb-8 max-w-[46ch] text-[16.5px] leading-[1.7] text-pretty text-lead">{!! $t('write', ['email' => '<a href="mailto:'.e($contactEmail).'">'.e($contactEmail).'</a>']) !!}</p>
            @endif
            <x-shared::button href="{{ route('mug.index') }}">{{ $t('shop') }}</x-shared::button>
        @endif
    </div>
</x-shared::layout>
