<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ffffff;font-family:Arial,sans-serif;color:#0b0b0f;">
    <tr>
        <td align="center" style="padding:48px 18px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;text-align:center;">
                <tr>
                    <td align="center" style="padding-bottom:32px;">
                        <div style="width:40px;height:40px;border-radius:50%;background:#145cff;color:#ffffff;font-weight:900;font-size:18px;line-height:40px;text-align:center;">&#9889;</div>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:36px;line-height:1.14;font-weight:900;padding-bottom:18px;">
                        Tarjeta enviada<br>a impresión
                    </td>
                </tr>
                <tr>
                    <td style="font-size:18px;line-height:1.5;font-weight:700;color:#6a6a6f;padding:0 12px 28px;">
                        Hola {{ $user->name }}, tu tarjeta <strong style="color:#0b0b0f;">{{ $card->name }}</strong>
                        quedó registrada para impresión
                        @if ($order)
                            con el pedido <strong style="color:#0b0b0f;">{{ $order->number }}</strong>.
                        @else
                            con la solicitud <strong style="color:#0b0b0f;">{{ $printJob->number }}</strong>.
                        @endif
                    </td>
                </tr>

                @if (count($evidenceFiles) > 0)
                    <tr>
                        <td style="padding:4px 0 30px;">
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    @foreach ($evidenceFiles as $file)
                                        <td width="50%" align="center" valign="top" style="padding:8px;">
                                            <img src="{{ $message->embed($file['path']) }}" alt="{{ $file['label'] ?? 'diseño' }}" style="display:block;width:100%;max-width:270px;border-radius:18px;border:1px solid #e5e7eb;box-shadow:0 10px 28px rgba(15,23,42,0.14);">
                                            <div style="font-size:15px;font-weight:800;color:#111827;text-transform:uppercase;letter-spacing:.04em;padding-top:12px;">
                                                {{ $file['label'] ?? 'diseño' }}
                                            </div>
                                        </td>
                                    @endforeach
                                </tr>
                            </table>
                        </td>
                    </tr>
                    <tr>
                        <td style="font-size:15px;line-height:1.5;color:#9a9aa1;padding-bottom:22px;">
                            También adjuntamos estos archivos para que queden como evidencia de la solicitud.
                        </td>
                    </tr>
                @else
                    <tr>
                        <td style="font-size:16px;line-height:1.55;color:#9a9aa1;padding-bottom:28px;">
                            No encontramos archivos de frente/reverso adjuntos en este momento. La solicitud quedó registrada correctamente.
                        </td>
                    </tr>
                @endif

                <tr>
                    <td style="font-size:15px;line-height:1.6;color:#6a6a6f;">
                        Estatus de tarjeta: <strong style="color:#0b0b0f;">{{ $card->status->label() }}</strong><br>
                        Estatus de impresión: <strong style="color:#0b0b0f;">{{ $printJob->status }}</strong>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
