<!DOCTYPE html>
<html lang="es">
<head><meta charset="utf-8"></head>
<body style="font-family: Arial, sans-serif; color:#222; background:#f5f6f5; margin:0; padding:24px;">
    <div style="max-width:560px; margin:0 auto; background:#fff; border-radius:8px; overflow:hidden; border:1px solid #e5e7eb;">
        <div style="background:#2e7d32; color:#fff; padding:18px 24px;">
            <h1 style="margin:0; font-size:18px;">MANZER Agroforestal</h1>
        </div>
        <div style="padding:24px;">
            <p style="margin:0 0 12px;">Hola {{ $trabajador?->nombre }},</p>
            <p style="margin:0 0 12px;">Adjuntamos tu recibo de nómina correspondiente a
                <strong>{{ $nomina->mes_nombre }} de {{ $nomina->anio }}</strong>.</p>
            <table style="width:100%; border-collapse:collapse; margin:16px 0;">
                <tr><td style="padding:6px 0; color:#666;">Periodo</td><td style="padding:6px 0; text-align:right;">{{ $nomina->mes_nombre }} {{ $nomina->anio }}</td></tr>
                <tr><td style="padding:6px 0; color:#666;">Líquido percibido</td><td style="padding:6px 0; text-align:right; font-weight:bold; color:#2e7d32;">{{ number_format($nomina->liquido, 2, ',', '.') }} €</td></tr>
            </table>
            <p style="margin:0 0 12px;">Encontrarás el detalle completo en el PDF adjunto. También puedes consultar tus nóminas desde tu portal.</p>
            <p style="margin:16px 0 0; color:#888; font-size:12px;">Este es un mensaje automático, por favor no respondas a este correo.</p>
        </div>
    </div>
</body>
</html>
