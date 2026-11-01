@props(['name', 'index', 'last', 'label', 'removed' => false])
{{-- For a saved row in a list card: ↑ ↓ save the whole card and move the row one place, „Usuń” drops it on save.
     Both arrows always hold their place (a missing one is greyed out), so the rows of a list line up; „Usuń” is a 44 px
     pill that turns into „Do usunięcia” once marked, since nothing goes before the card is saved (MA-335). --}}
@php($arrow = 'grid size-11 place-items-center rounded-full border text-[16px]')
<div class="mt-2 flex items-center gap-2">
    @if ($index > 0)
        <button name="move" value="{{ $index }}:up" aria-label="Przesuń wyżej: {{ $label }}" class="{{ $arrow }} border-line-strong text-ink hover:border-ink hover:bg-sand-dark">↑</button>
    @else
        <span aria-hidden="true" class="{{ $arrow }} border-line text-hint opacity-50">↑</span>
    @endif
    @if ($index < $last)
        <button name="move" value="{{ $index }}:down" aria-label="Przesuń niżej: {{ $label }}" class="{{ $arrow }} border-line-strong text-ink hover:border-ink hover:bg-sand-dark">↓</button>
    @else
        <span aria-hidden="true" class="{{ $arrow }} border-line text-hint opacity-50">↓</span>
    @endif
    <x-shared::admin.remove-toggle :name="$name.'[remove]'" :label="$label" :checked="$removed" class="ml-auto" />
</div>
