<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #222; margin: 0; }
        .wrap { padding: 24px 28px; }
        .header { border-bottom: 2px solid #2e7d32; padding-bottom: 10px; margin-bottom: 16px; }
        .empresa { font-size: 18px; font-weight: bold; color: #2e7d32; }
        .empresa small { display:block; font-size: 10px; color:#666; font-weight: normal; }
        .titulo { text-align: right; }
        .titulo h1 { font-size: 16px; margin: 0; }
        .titulo .periodo { font-size: 12px; color:#555; }
        table { width: 100%; border-collapse: collapse; }
        .datos td { padding: 3px 4px; vertical-align: top; }
        .datos .lbl { color:#666; width: 130px; }
        .box { border:1px solid #ddd; border-radius:4px; margin-top: 14px; }
        .box h2 { font-size: 12px; margin:0; padding:6px 10px; background:#f4f6f4; border-bottom:1px solid #ddd; }
        .lineas td { padding:6px 10px; border-bottom:1px solid #eee; }
        .lineas .imp { text-align:right; }
        .tot td { padding:8px 10px; font-weight:bold; font-size: 14px; }
        .tot .liq { color:#2e7d32; }
        .rojo { color:#b00020; }
        .footer { margin-top: 22px; font-size: 10px; color:#888; border-top:1px solid #eee; padding-top:8px; }
        .firma { margin-top: 40px; }
        .firma td { width:50%; font-size:10px; color:#666; }
        .firma .linea { border-top:1px solid #999; padding-top:4px; }
    </style>
</head>
<body>
<div class="wrap">
    <table class="header">
        <tr>
            <td>
                <div class="empresa">MANZER Agroforestal, S.L.R.U.
                    <small>Recibo individual de salarios</small>
                </div>
            </td>
            <td class="titulo">
                <h1>Nómina</h1>
                <div class="periodo">{{ $nomina->mes_nombre }} {{ $nomina->anio }}</div>
            </td>
        </tr>
    </table>

    <table class="datos">
        <tr>
            <td class="lbl">Trabajador/a:</td>
            <td><strong>{{ $nomina->trabajador?->nombre }} {{ $nomina->trabajador?->apellidos }}</strong></td>
            <td class="lbl">DNI/NIE:</td>
            <td>{{ $nomina->trabajador?->dni }}</td>
        </tr>
        <tr>
            <td class="lbl">Categoría:</td>
            <td>{{ $nomina->trabajador?->categoria_convenio ?: '—' }}</td>
            <td class="lbl">Fecha de alta:</td>
            <td>{{ optional($nomina->trabajador?->fecha_alta)->format('d/m/Y') ?: '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Periodo:</td>
            <td>{{ $nomina->mes_nombre }} de {{ $nomina->anio }}</td>
            <td class="lbl">IBAN:</td>
            <td>{{ $nomina->trabajador?->iban ?: '—' }}</td>
        </tr>
    </table>

    <div class="box">
        <h2>Devengos</h2>
        <table class="lineas">
            <tr>
                <td>Salario bruto</td>
                <td class="imp">{{ number_format($nomina->salario_bruto, 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td><strong>Total devengado (A)</strong></td>
                <td class="imp"><strong>{{ number_format($nomina->salario_bruto, 2, ',', '.') }} €</strong></td>
            </tr>
        </table>
    </div>

    <div class="box">
        <h2>Deducciones</h2>
        <table class="lineas">
            <tr>
                <td>Seguridad Social a cargo del trabajador</td>
                <td class="imp rojo">− {{ number_format($nomina->ss_trabajador, 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td>Retención IRPF</td>
                <td class="imp rojo">− {{ number_format($nomina->irpf, 2, ',', '.') }} €</td>
            </tr>
            <tr>
                <td><strong>Total deducciones (B)</strong></td>
                <td class="imp rojo"><strong>− {{ number_format($nomina->ss_trabajador + $nomina->irpf, 2, ',', '.') }} €</strong></td>
            </tr>
        </table>
    </div>

    <div class="box">
        <table class="tot">
            <tr>
                <td>LÍQUIDO A PERCIBIR (A − B)</td>
                <td class="imp liq">{{ number_format($nomina->liquido, 2, ',', '.') }} €</td>
            </tr>
        </table>
    </div>

    @if($nomina->notas)
        <div class="footer"><strong>Notas:</strong> {{ $nomina->notas }}</div>
    @endif

    <div class="footer">
        Seguridad Social a cargo de la empresa: {{ number_format($nomina->ss_empresa, 2, ',', '.') }} € ·
        Coste total empresa: {{ number_format($nomina->coste_empresa, 2, ',', '.') }} €
    </div>

    <table class="firma">
        <tr>
            <td><div class="linea">Firma de la empresa</div></td>
            <td><div class="linea">Recibí (firma del trabajador/a)</div></td>
        </tr>
    </table>
</div>
</body>
</html>
