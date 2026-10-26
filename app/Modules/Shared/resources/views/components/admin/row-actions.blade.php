@props(['name', 'index', 'last', 'label', 'removed' => false])
{{-- For a saved row in a list card: ↑ ↓ save the whole card and move the row one place, „usuń” drops it on save. --}}
@php($arrow = 'grid size-11 place-items-center rounded-full border border-line-strong text-[16px] text-ink hover:border-ink hover:bg-sand-dark')
<div class="mt-2 flex flex-wrap items-center gap-2">
    @if ($index > 0)
        <button name="move" value="{{ $index }}:up" aria-label="Przesuń wyżej: {{ $label }}" class="{{ $arrow }}">↑</button>
    @endif
    @if ($index < $last)
        <button name="move" value="{{ $index }}:down" aria-label="Przesuń niżej: {{ $label }}" class="{{ $arrow }}">↓</button>
    @endif
    <label class="ml-auto flex min-h-11 items-center gap-1.5 text-[12.5px] text-label">
        <input type="checkbox" name="{{ $name }}[remove]" value="1" @checked($removed) class="size-4 accent-error"> usuń
    </label>
</div>
