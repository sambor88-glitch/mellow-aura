@php
    $t = fn (string $key, array $replace = []) => __('gifts::choice.page.'.$key, $replace);
@endphp
<x-shared::layout :title="$t('title')" :description="$t('description')" :canonical="route('gift-choice.index')" noindex>
    <div class="mx-auto max-w-[640px] animate-ma-view px-[clamp(18px,4vw,48px)] pt-12 pb-24">
        <p class="eyebrow mb-4">{{ $t('eyebrow') }}</p>
        <h1 class="mb-5 font-serif text-[length:clamp(40px,5.2vw,72px)] leading-[.96] font-light tracking-[-0.025em] text-balance"><span class="blur-in">{{ $t('heading') }}</span></h1>
        <p class="mb-8 max-w-[46ch] text-[16.5px] leading-[1.7] text-pretty text-lead">{{ $t('lead') }}</p>

        <form method="post" action="{{ route('gift-choice.find') }}" novalidate class="glass rounded-[26px] px-[clamp(20px,3vw,34px)] py-[clamp(22px,3vw,30px)]">
            @csrf
            <x-shared::field name="code" :label="$t('code')" :hint="$t('code_hint')" autocomplete="off" autocapitalize="characters" spellcheck="false" maxlength="20" placeholder="MA-____-____" />
            <x-shared::button class="mt-5 w-full">{{ $t('find') }}</x-shared::button>
        </form>
    </div>
</x-shared::layout>
