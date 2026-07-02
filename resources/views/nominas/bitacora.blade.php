@extends('layouts.app')

@section('title', 'Bitácora de nóminas')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Bitácora de cargas de nóminas</h1>
            <p class="text-muted mb-0">Historial de todas las cargas masivas realizadas.</p>
        </div>
        <a href="{{ route('nominas.resumen') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Volver al resumen
        </a>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Archivo</th>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Periodo</th>
                        <th class="text-center">Creadas</th>
                        <th class="text-center">Omitidas</th>
                        <th class="text-center">Errores</th>
                        <th>Estado</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($importaciones as $imp)
                    <tr>
                        <td class="fw-medium"><i class="bi bi-file-earmark-excel text-success me-1"></i>{{ $imp->archivo_nombre }}</td>
                        <td class="small">{{ $imp->created_at->format('d/m/Y H:i') }}</td>
                        <td class="small">{{ $imp->user->name ?? '—' }}</td>
                        <td class="small">{{ $imp->mes_nombre }} {{ $imp->anio }}</td>
                        <td class="text-center text-success fw-semibold">{{ $imp->creadas }}</td>
                        <td class="text-center text-warning">{{ $imp->omitidas }}</td>
                        <td class="text-center {{ $imp->con_error ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $imp->con_error }}</td>
                        <td>
                            @if($imp->estado === 'fallida')
                                <span class="badge bg-danger">Fallida</span>
                            @elseif($imp->estado === 'con_omitidas')
                                <span class="badge bg-warning text-dark">Con omitidas</span>
                            @else
                                <span class="badge bg-success">Procesada</span>
                            @endif
                        </td>
                        <td class="text-end">
                            <a href="{{ route('nominas.bitacora.detalle', $imp) }}" class="btn btn-sm btn-outline-primary">Ver detalle</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">Aún no hay cargas registradas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
