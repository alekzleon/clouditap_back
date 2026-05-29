<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#ffffff;font-family:Arial,sans-serif;color:#0b0b0f;">
    <tr>
        <td align="center" style="padding:56px 18px;">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:460px;text-align:center;">
                <tr>
                    <td align="center" style="padding-bottom:42px;">
                        <div style="width:40px;height:40px;border-radius:50%;background:#145cff;color:#ffffff;font-weight:900;font-size:18px;line-height:40px;text-align:center;">&#9889;</div>
                    </td>
                </tr>
                <tr>
                    <td style="font-size:42px;line-height:1.12;font-weight:900;padding-bottom:24px;">
                        Compra<br>confirmada
                    </td>
                </tr>
                <tr>
                    <td style="font-size:19px;line-height:1.5;font-weight:700;color:#6a6a6f;padding:0 8px 30px;">
                        Recibimos tu pago por {{ $order->quantity }}
                        {{ $order->quantity === 1 ? 'tarjeta' : 'tarjetas' }}. Ya puedes continuar en TapCloudi.
                    </td>
                </tr>
                <tr>
                    <td style="background:#f8fafc;border:1px solid #e5e7eb;border-radius:14px;padding:18px;font-size:16px;line-height:1.7;color:#4b5563;">
                        <strong style="color:#0b0b0f;">Orden:</strong> {{ $order->number }}<br>
                        <strong style="color:#0b0b0f;">Total:</strong> {{ number_format($order->amount / 100, 2) }} {{ strtoupper($order->currency) }}<br>
                        <strong style="color:#0b0b0f;">Estatus:</strong> {{ $order->status }}
                    </td>
                </tr>
                @if ($order->shipping_address)
                    <tr>
                        <td style="font-size:15px;line-height:1.55;color:#9a9aa1;padding-top:24px;">
                            Envío para {{ $order->shipping_address['recipient_name'] ?? '' }}:
                            {{ $order->shipping_address['street'] ?? '' }} {{ $order->shipping_address['exterior_number'] ?? '' }},
                            {{ $order->shipping_address['city'] ?? '' }}.
                        </td>
                    </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
