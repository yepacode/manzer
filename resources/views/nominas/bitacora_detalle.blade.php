@extends('layouts.app')

@section('title', 'Detalle de carga')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Detalle de la carga</h1>
            <p class="text-muted mb-0">
                <i class="bi bi-file-earmark-excel text-success me-1"></i>{{ $importacion->archivo_nombre }}
                · {{ $importacion->created_at->format('d/m/Y H:i') }}
                · {{ $importacion->user->name ?? '—' }}
                · {{ $importacion->mes_nombre }} {{ $importacion->anio }}
            </p>
        </div>
        <a href="{{ route('nominas.bitacora') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Volver a la bitácora
        </a>
    </div>

    {{-- Resumen de la carga --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Total filas</small><h4 class="mb-0">{{ $importacion->total_filas }}</h4></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Creadas</small><h4 class="mb-0 text-success">{{ $importacion->creadas }}</h4></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Omitidas</small><h4 class="mb-0 text-warning">{{ $importacion->omitidas }}</h4></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Con error</small><h4 class="mb-0 {{ $importacion->con_error ? 'text-danger' : '' }}">{{ $importacion->con_error }}</h4></div></div></div>
    </div>

    {{-- Nóminas creadas --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-header bg-white py-3"><h5 class="mb-0">Nóminas creadas ({{ $importacion->nominas->count() }})</h5></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Trabajador</th><th class="text-end">Bruto</th><th class="text-end">SS trab.</th><th class="text-end">IRPF</th><th class="text-end">Líquido</th><th class="text-center">PDF</th></tr>
                </thead>
                <tbody>
                    @forelse($importacion->nominas as $nom)
                    <tr>
                        <td class="fw-medium">{{ $nom->trabajador?->apellidos }}, {{ $nom->trabajador?->nombre }} <span class="small text-muted">({{ $nom->trabajador?->dni }})</span></td>
                        <td class="text-end">{{ number_format($nom->salario_bruto, 2, ',', '.') }} €</td>
                        <td class="text-end">{{ number_format($nom->ss_trabajador, 2, ',', '.') }} €</td>
                        <td class="text-end">{{ number_format($nom->irpf, 2, ',', '.') }} €</td>
                        <td class="text-end fw-semibold">{{ number_format($nom->liquido, 2, ',', '.') }} €</td>
                        <td class="text-center"><a href="{{ route('nominas.recibo', $nom) }}" target="_blank" class="btn btn-sm btn-outline-success" title="Ver recibo"><i class="bi bi-file-earmark-pdf"></i></a></td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center text-muted py-3">No hay nóminas creadas en esta carga (o fueron eliminadas).</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Filas omitidas / con error --}}
    @if(!empty($importacion->detalle))
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><h5 class="mb-0">Filas omitidas o con error</h5></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th style="width:120px">Fila</th><th style="width:180px">DNI</th><th>Motivo</th></tr>
                </thead>
                <tbody>
                    @foreach($importacion->detalle as $d)
                    <tr>
                        <td>{{ $d['fila'] ?? '—' }}</td>
                        <td>{{ $d['dni'] ?? '—' }}</td>
                        <td class="small">{{ $d['motivo'] ?? '' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
