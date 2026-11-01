@props(['name', 'label' => null, 'checked' => false])
{{-- „Usuń” for a saved row of a list card: a 44 px pill that turns into „Do usunięcia” once marked, since the row only goes
     when the card is saved (MA-335). Replaces the bare 16 px checkbox. --}}
<label {{ $attributes->class('inline-flex min-h-11 flex-none cursor-pointer items-center gap-2 rounded-full border border-line-strong px-4 text-[13px] text-lead hover:border-error hover:text-error has-[:checked]:border-alert-line has-[:checked]:bg-alert has-[:checked]:text-alert-text has-[:focus-visible]:outline-2 has-[:focus-visible]:outline-offset-2 has-[:focus-visible]:outline-navy') }}>
    <input type="checkbox" name="{{ $name }}" value="1" @checked($checked) class="peer sr-only">
    <span class="peer-checked:hidden">Usuń</span>
    <span class="hidden peer-checked:inline">Do usunięcia — zapisz</span>
    @if ($label)
        <span class="sr-only">: {{ $label }}</span>
    @endif
</label>
