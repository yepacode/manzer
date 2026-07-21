<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 11px; color: #222; margin: 0; }
        .wrap { padding: 22px 26px; }
        .header { border-bottom: 2px solid #2e7d32; padding-bottom: 8px; margin-bottom: 14px; }
        .empresa { font-size: 16px; font-weight: bold; color: #2e7d32; }
        .empresa small { display:block; font-size: 9px; color:#666; font-weight: normal; }
        .titulo { text-align: right; }
        .titulo h1 { font-size: 15px; margin: 0; }
        .titulo .periodo { font-size: 11px; color:#555; }
        table { width: 100%; border-collapse: collapse; }
        .datos td { padding: 2px 4px; vertical-align: top; }
        .datos .lbl { color:#666; width: 110px; }
        .box { border:1px solid #ddd; border-radius:4px; margin-top: 12px; }
        .box h2 { font-size: 11px; margin:0; padding:5px 9px; background:#f4f6f4; border-bottom:1px solid #ddd; }
        .lineas td { padding:4px 9px; border-bottom:1px solid #eee; }
        .lineas .imp { text-align:right; white-space:nowrap; }
        .lineas .sub td { border-top:1px solid #ccc; font-weight:bold; background:#fafafa; }
        .tot td { padding:7px 9px; font-weight:bold; font-size: 13px; }
        .tot .liq { color:#2e7d32; text-align:right; }
        .rojo { color:#b00020; }
        .cols { width:100%; }
        .cols td { vertical-align: top; width:50%; }
        .cols td:first-child { padding-right:8px; }
        .cols td:last-child { padding-left:8px; }
        .footer { margin-top: 16px; font-size: 9px; color:#888; border-top:1px solid #eee; padding-top:6px; }
        .firma { margin-top: 34px; }
        .firma td { width:50%; font-size:9px; color:#666; }
        .firma .linea { border-top:1px solid #999; padding-top:4px; }
    </style>
</head>
<body>
@php
    $empresaRazon = $nomina->importacion?->empresa_razon ?: 'MANZER Agroforestal, S.L.R.U.';
    $empresaNif = $nomina->importacion?->empresa_nif;
    $devengos = $nomina->conceptos->where('tipo', 'devengo');
    $deducciones = $nomina->conceptos->where('tipo', 'deduccion');
    $bases = $nomina->conceptos->where('tipo', 'base');
    $totalDeducir = round($nomina->salario_bruto - $nomina->liquido, 2);
    $fmt = fn($n) => number_format((float) $n, 2, ',', '.') . ' €';
@endphp
<div class="wrap">
    <table class="header">
        <tr>
            <td>
                <div class="empresa">{{ $empresaRazon }}
                    <small>{{ $empresaNif ? 'NIF: '.$empresaNif.' · ' : '' }}Recibo individual de salarios</small>
                </div>
            </td>
            <td class="titulo">
                <h1>Nómina</h1>
                <div class="periodo">{{ $nomina->mes_nombre }} {{ $nomina->anio }}</div>
                @if($nomina->codigo_nomina)<div class="periodo" style="font-size:9px">Ref. {{ $nomina->codigo_nomina }}</div>@endif
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
            <td class="lbl">Periodo:</td>
            <td>{{ $nomina->mes_nombre }} de {{ $nomina->anio }}</td>
        </tr>
    </table>

    @if($nomina->conceptos->isNotEmpty())
        {{-- ===== RECIBO OFICIAL (con desglose de conceptos) ===== --}}
        <table class="cols">
            <tr>
                <td>
                    <div class="box">
                        <h2>Devengos</h2>
                        <table class="lineas">
                            @foreach($devengos as $c)
                                <tr><td>{{ $c->concepto }}</td><td class="imp">{{ $fmt($c->importe) }}</td></tr>
                            @endforeach
                            <tr class="sub"><td>A. Total devengado</td><td class="imp">{{ $fmt($nomina->salario_bruto) }}</td></tr>
                        </table>
                    </div>
                </td>
                <td>
                    <div class="box">
                        <h2>Deducciones</h2>
                        <table class="lineas">
                            @forelse($deducciones as $c)
                                <tr><td>{{ $c->concepto }}</td><td class="imp rojo">− {{ $fmt($c->importe) }}</td></tr>
                            @empty
                                <tr><td colspan="2" class="imp" style="text-align:center;color:#999">Sin deducciones</td></tr>
                            @endforelse
                            <tr class="sub"><td>B. Total a deducir</td><td class="imp rojo">− {{ $fmt($totalDeducir) }}</td></tr>
                        </table>
                    </div>
                </td>
            </tr>
        </table>

        <div class="box">
            <table class="tot">
                <tr>
                    <td>LÍQUIDO TOTAL A PERCIBIR (A − B)</td>
                    <td class="liq">{{ $fmt($nomina->liquido) }}</td>
                </tr>
            </table>
        </div>

        @if($bases->isNotEmpty())
            <div class="box">
                <h2>Determinación de las bases de cotización</h2>
                <table class="lineas">
                    @foreach($bases as $c)
                        <tr><td>{{ $c->concepto }}</td><td class="imp">{{ $fmt($c->importe) }}</td></tr>
                    @endforeach
                </table>
            </div>
        @endif
    @else
        {{-- ===== RECIBO SIMPLE (nóminas manuales sin desglose) ===== --}}
        <div class="box">
            <h2>Devengos</h2>
            <table class="lineas">
                <tr><td>Salario bruto</td><td class="imp">{{ $fmt($nomina->salario_bruto) }}</td></tr>
                <tr class="sub"><td>A. Total devengado</td><td class="imp">{{ $fmt($nomina->salario_bruto) }}</td></tr>
            </table>
        </div>
        <div class="box">
            <h2>Deducciones</h2>
            <table class="lineas">
                <tr><td>Seguridad Social a cargo del trabajador</td><td class="imp rojo">− {{ $fmt($nomina->ss_trabajador) }}</td></tr>
                <tr><td>Retención IRPF</td><td class="imp rojo">− {{ $fmt($nomina->irpf) }}</td></tr>
                <tr class="sub"><td>B. Total a deducir</td><td class="imp rojo">− {{ $fmt($nomina->ss_trabajador + $nomina->irpf) }}</td></tr>
            </table>
        </div>
        <div class="box">
            <table class="tot">
                <tr><td>LÍQUIDO A PERCIBIR (A − B)</td><td class="liq">{{ $fmt($nomina->liquido) }}</td></tr>
            </table>
        </div>
    @endif

    @if($nomina->notas)
        <div class="footer"><strong>Notas:</strong> {{ $nomina->notas }}</div>
    @endif
    <div class="footer">
        Seguridad Social a cargo de la empresa: {{ $fmt($nomina->ss_empresa) }} ·
        Coste total empresa: {{ $fmt($nomina->coste_empresa) }}
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
