<x-shared::mail.layout
    :title="'Odstąpienie od umowy: '.$withdrawal->order_number"
    :preheader="$withdrawal->name.' · '.$withdrawal->scope->label().' · '.$withdrawal->submittedAtLabel()"
>
    <h1 style="margin: 0 0 12px; font-family: Georgia, 'Times New Roman', serif; font-weight: normal; font-size: 28px; line-height: 1.15; color: #2F2620;">Odstąpienie od umowy {{ $withdrawal->order_number }}</h1>
    <p style="margin: 0 0 22px; color: #5C5043;">
        Oświadczenie przyszło {{ $withdrawal->submittedAtLabel() }} przez formularz „Odstąp od umowy tutaj”.
        Potwierdzenie z treścią i godziną poszło już na adres {{ $withdrawal->email }}. Odpowiedź na tego maila trafi prosto do tej osoby.
    </p>

    @if ($withdrawal->order === null)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 24px;">
            <tr>
                <td style="background: #F7ECE9; border: 1px solid #E0C3BC; border-radius: 4px; padding: 14px 16px; font-size: 14.5px; line-height: 1.6; color: #7A3A2E;">
                    Nie znalazłam zamówienia {{ $withdrawal->order_number }} złożonego z adresu {{ $withdrawal->email }}.
                    Sprawdź je ręcznie, zanim odpiszesz — oświadczenie liczy się tak samo.
                </td>
            </tr>
        </table>
    @endif

    <x-checkout::mail.withdrawal-statement :withdrawal="$withdrawal" />

    <p style="margin: 0 0 22px; color: #2F2620;">
        Pieniądze trzeba zwrócić w ciągu 14 dni od dziś. Możesz z tym poczekać, aż rzeczy wrócą albo dostaniesz dowód ich nadania.
    </p>

    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td style="background: #2F2620; border-radius: 999px;">
                <a href="{{ $withdrawal->order ? route('admin.orders.show', $withdrawal->order) : route('admin.withdrawals.index') }}" style="display: inline-block; padding: 13px 26px; font-size: 14px; color: #F7F2EA; text-decoration: none;">{{ $withdrawal->order ? 'Otwórz zamówienie w panelu' : 'Otwórz odstąpienia w panelu' }}</a>
            </td>
        </tr>
    </table>

    <x-slot:footer>
        Powiadomienie ze sklepu MellowAura. Wysyłam je na adres kontaktowy z ustawień panelu.
    </x-slot:footer>
</x-shared::mail.layout>
