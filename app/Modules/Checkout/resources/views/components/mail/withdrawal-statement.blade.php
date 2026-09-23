@props(['withdrawal'])
{{-- The statement exactly as it came in, with the moment it arrived. The same block goes to the customer and to Kasia. --}}
@php
    $label = 'padding: 4px 14px 4px 0; vertical-align: top; white-space: nowrap; font-size: 13px; color: #726456;';
    $value = 'padding: 4px 0; vertical-align: top; font-size: 14.5px; color: #2F2620; word-break: break-word;';
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
    <tr>
        <td style="background: #F7F2EA; border: 1px solid #E6DCCD; border-radius: 4px; padding: 16px 18px;">
            <div style="margin: 0 0 8px; font-size: 11px; letter-spacing: 2px; text-transform: uppercase; color: #726456;">Treść oświadczenia</div>
            <p style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-size: 17px; line-height: 1.5; color: #2F2620; white-space: pre-line;">{{ $withdrawal->statement() }}</p>
            <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                <tr><td style="{{ $label }}">Imię i nazwisko</td><td style="{{ $value }}">{{ $withdrawal->name }}</td></tr>
                <tr><td style="{{ $label }}">E-mail</td><td style="{{ $value }}">{{ $withdrawal->email }}</td></tr>
                <tr><td style="{{ $label }}">Numer zamówienia</td><td style="{{ $value }}">{{ $withdrawal->order_number }}</td></tr>
                <tr><td style="{{ $label }}">Zakres</td><td style="{{ $value }}">{{ $withdrawal->scope->label() }}</td></tr>
                @if ($withdrawal->items !== null)
                    <tr><td style="{{ $label }}">Rzeczy</td><td style="{{ $value }} white-space: pre-line;">{{ $withdrawal->items }}</td></tr>
                @endif
                <tr><td style="{{ $label }}">Wysłane</td><td style="{{ $value }}">{{ $withdrawal->submittedAtLabel() }}</td></tr>
            </table>
        </td>
    </tr>
</table>
