<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ffffff;font-family:Arial,sans-serif;color:#0b0b0f;">
    <tr>
        <td align="center" style="padding:56px 18px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:420px;text-align:center;">
                <tr>
                    <td align="center" style="padding-bottom:42px;">
                        <div style="width:40px;height:40px;border-radius:50%;background:#145cff;color:#ffffff;font-weight:900;font-size:18px;line-height:40px;text-align:center;">&#9889;</div>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:42px;line-height:1.12;font-weight:900;padding-bottom:26px;">
                        {{ $milestone }} visitas<br>alcanzadas
                    </td>
                </tr>
                <tr>
                    <td style="font-size:19px;line-height:1.5;font-weight:700;color:#6a6a6f;padding:0 8px 34px;">
                        Tu tarjeta virtual
                        <strong style="color:#0b0b0f;">
                            @if ($card)
                                {{ $card->name }}
                            @else
                                {{ $linkPage->profile['name'] ?? 'TapCloudi' }}
                            @endif
                        </strong>
                        llegó a {{ $milestone }} visitas.
                    </td>
                </tr>
                <tr>
                    <td style="font-size:16px;line-height:1.55;color:#b4b4ba;">
                        Sigue compartiéndola para que más personas lleguen a tus datos y canales de contacto.
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
