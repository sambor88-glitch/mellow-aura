<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 22px; border-top: 1px solid #E2D7C7;">
    @foreach ($firingRequest->summary() as $label => $value)
        <tr>
            <td style="padding: 10px 12px 10px 0; border-bottom: 1px solid #E2D7C7; font-size: 13px; color: #726456; white-space: nowrap; vertical-align: top;">{{ $label }}</td>
            <td style="padding: 10px 0; border-bottom: 1px solid #E2D7C7; color: #2F2620; text-align: right; @if ($label === 'Wycena') font-weight: bold; @endif">{{ $value }}</td>
        </tr>
    @endforeach
</table>
