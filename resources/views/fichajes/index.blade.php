@extends('layouts.app')

@section('title', 'Fichajes')

@push('styles')
<style>
    /* Sticky actions column */
    .table-sticky-actions {
        position: relative;
    }
    .table-sticky-actions th:last-child {
        position: sticky;
        right: 0;
        background-color: #f8f9fa;
        box-shadow: -2px 0 5px rgba(0,0,0,0.1);
        z-index: 1;
    }
    .table-sticky-actions td:last-child {
        position: sticky;
        right: 0;
        background-color: #fff;
        box-shadow: -2px 0 5px rgba(0,0,0,0.1);
        z-index: 1;
    }
    .table-sticky-actions tr:hover td:last-child {
        background-color: #f8f9fa;
    }
    .table-sticky-actions tr.table-info td:last-child {
        background-color: #cfe2ff;
    }
    /* Ajustar botones de acciones */
    .table-sticky-actions .btn-group-sm .btn {
        padding: 0.2rem 0.4rem;
    }
    /* Loading overlay */
    .loading-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255,255,255,0.8);
        z-index: 9999;
        justify-content: center;
        align-items: center;
    }
    .loading-overlay.active {
        display: flex;
    }
    /* Quick search highlight */
    .highlight {
        background-color: #fff3cd;
        padding: 0 2px;
        border-radius: 2px;
    }
    /* Row clickable */
    .row-clickable {
        cursor: pointer;
    }
    .row-clickable:hover {
        background-color: #f8f9fa !important;
    }
</style>
@endpush

@section('content')
<!-- Loading Overlay -->
<div class="loading-overlay" id="loadingOverlay">
    <div class="text-center">
        <div class="spinner-border text-primary mb-2" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <p class="text-muted mb-0">Procesando...</p>
    </div>
</div>

<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">
                @if($esTrabajador ?? false)
                    Mis Fichajes
                @else
                    Fichajes
                @endif
            </h1>
            <p class="text-muted mb-0">
                @if($esTrabajador ?? false)
                    Control de tus entradas y salidas
                @else
                    Control de entrada y salida de trabajadores
                @endif
            </p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            @if(!($esTrabajador ?? false))
                <!-- Export Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="bi bi-download me-1"></i>Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <a class="dropdown-item" href="{{ route('fichajes.export.excel', request()->query()) }}">
                                <i class="bi bi-file-earmark-excel me-2 text-success"></i>Excel (.xlsx)
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('fichajes.export.pdf', request()->query()) }}">
                                <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>PDF
                            </a>
                        </li>
                    </ul>
                </div>

                <a href="{{ route('fichajes.resumen') }}" class="btn btn-outline-secondary">
                    <i class="bi bi-bar-chart me-2"></i>Resumen Mensual
                </a>
            @endif

            @if($esTrabajador ?? false)
                {{-- Botón de fichar para trabajadores --}}
                {{-- $fichajeHoy ahora solo contiene fichaje ABIERTO (sin salida) --}}
                @if($fichajeHoy)
                    {{-- Tiene fichaje abierto, mostrar botón de salida --}}
                    <button type="button" class="btn btn-danger" id="btnFicharSalida">
                        <i class="bi bi-box-arrow-right me-2"></i>Fichar Salida
                    </button>
                @else
                    {{-- No tiene fichaje abierto, puede fichar entrada (permite múltiples por día) --}}
                    <a href="{{ route('fichajes.create') }}" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>Fichar Entrada
                    </a>
                @endif
            @else
                @can('crear_fichajes')
                <a href="{{ route('fichajes.create') }}" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-2"></i>Nuevo Fichaje
                </a>
                @endcan
            @endif
        </div>
    </div>

    @if($esTrabajador ?? false)
        {{-- Alerta del estado de hoy para trabajadores --}}
        {{-- $fichajeHoy ahora solo contiene fichaje ABIERTO (sin salida) --}}
        @if($fichajeHoy)
            {{-- Tiene fichaje abierto --}}
            <div class="alert alert-info mb-4">
                <div class="d-flex align-items-center">
                    <i class="bi bi-clock fs-4 me-3"></i>
                    <div>
                        <strong>Hoy {{ now()->format('d/m/Y') }}</strong><br>
                        Entrada: <strong>{{ \Carbon\Carbon::parse($fichajeHoy->hora_entrada)->format('H:i') }}</strong>
                        - <span class="text-warning">Pendiente fichar salida</span>
                    </div>
                </div>
            </div>
        @else
            {{-- No tiene fichaje abierto - puede iniciar nuevo fichaje --}}
            <div class="alert alert-warning mb-4">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>No tienes fichaje abierto.</strong> Usa el botón "Fichar Entrada" para comenzar o continuar tu jornada.
            </div>
        @endif
    @endif

    <!-- Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-primary bg-opacity-10 p-3 rounded">
                                <i class="bi bi-clock-history text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">{{ ($esTrabajador ?? false) ? 'Mis Fichajes' : 'Total Fichajes' }}</h6>
                            <h3 class="mb-0">{{ number_format($stats['total_fichajes']) }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-warning bg-opacity-10 p-3 rounded">
                                <i class="bi bi-exclamation-triangle text-warning fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Pendientes</h6>
                            <h3 class="mb-0">{{ number_format($stats['pendientes_validar']) }}</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-success bg-opacity-10 p-3 rounded">
                                <i class="bi bi-hourglass-split text-success fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Horas Mes</h6>
                            <h3 class="mb-0">{{ number_format($stats['horas_totales'], 1) }}h</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="bg-info bg-opacity-10 p-3 rounded">
                                <i class="bi bi-clock-fill text-info fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Horas Extra</h6>
                            <h3 class="mb-0">{{ number_format($stats['horas_extra'], 1) }}h</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form action="{{ route('fichajes.index') }}" method="GET" class="row g-3" id="filterForm">
                <div class="col-md-2">
                    <label class="form-label">Fecha Desde</label>
                    <input type="date" name="fecha_desde" class="form-control"
                           value="{{ request('fecha_desde', now()->startOfMonth()->format('Y-m-d')) }}">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Fecha Hasta</label>
                    <input type="date" name="fecha_hasta" class="form-control"
                           value="{{ request('fecha_hasta', now()->endOfMonth()->format('Y-m-d')) }}">
                </div>

                @if(!($esTrabajador ?? false))
                    <div class="col-md-2">
                        <label class="form-label">Trabajador</label>
                        <select name="trabajador_id" class="form-select">
                            <option value="">Todos</option>
                            @foreach($trabajadores as $trabajador)
                                <option value="{{ $trabajador->id }}" {{ request('trabajador_id') == $trabajador->id ? 'selected' : '' }}>
                                    {{ $trabajador->nombre }} {{ $trabajador->apellidos }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Cuadrilla</label>
                        <select name="cuadrilla_id" class="form-select">
                            <option value="">Todas</option>
                            @foreach($cuadrillas as $cuadrilla)
                                <option value="{{ $cuadrilla->id }}" {{ request('cuadrilla_id') == $cuadrilla->id ? 'selected' : '' }}>
                                    {{ $cuadrilla->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Obra</label>
                        <select name="obra_id" class="form-select">
                            <option value="">Todas</option>
                            @foreach($obras as $obra)
                                <option value="{{ $obra->id }}" {{ request('obra_id') == $obra->id ? 'selected' : '' }}>
                                    {{ $obra->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Estado</label>
                        <select name="validado" class="form-select">
                            <option value="">Todos</option>
                            <option value="1" {{ request('validado') === '1' ? 'selected' : '' }}>Validados</option>
                            <option value="0" {{ request('validado') === '0' ? 'selected' : '' }}>Pendientes</option>
                        </select>
                    </div>
                @else
                    <div class="col-md-3">
                        <label class="form-label">Obra</label>
                        <select name="obra_id" class="form-select">
                            <option value="">Todas</option>
                            @foreach($obras as $obra)
                                <option value="{{ $obra->id }}" {{ request('obra_id') == $obra->id ? 'selected' : '' }}>
                                    {{ $obra->nombre }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary" id="btnBuscar">
                        <i class="bi bi-search"></i>
                    </button>
                    <a href="{{ route('fichajes.index') }}" class="btn btn-outline-secondary" title="Limpiar filtros">
                        <i class="bi bi-x-lg"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Quick Search -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-transparent border-end-0">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" class="form-control border-start-0" id="quickSearch"
                               placeholder="Buscar en tabla... (trabajador, obra, fecha)">
                    </div>
                </div>
                <div class="col-md-8 text-end">
                    <small class="text-muted">
                        Mostrando <strong id="visibleCount">{{ $fichajes->count() }}</strong> de <strong>{{ $fichajes->total() }}</strong> fichajes
                    </small>
                </div>
            </div>
        </div>
    </div>

    <!-- Table -->
    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            @if(!($esTrabajador ?? false))
            <form id="validarMultipleForm" action="{{ route('fichajes.validar-multiple') }}" method="POST">
                @csrf
            @endif
                <div class="table-responsive">
                    <table class="table table-hover mb-0 table-sticky-actions" id="fichajesTable">
                        <thead class="table-light">
                            <tr>
                                @if(!($esTrabajador ?? false))
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="selectAll"
                                           title="Seleccionar todos los pendientes" data-bs-toggle="tooltip">
                                </th>
                                @endif
                                <th>Fecha</th>
                                @if(!($esTrabajador ?? false))
                                <th>Trabajador</th>
                                @endif
                                <th>Obra</th>
                                <th class="text-center">Entrada</th>
                                <th class="text-center">Salida</th>
                                <th class="text-center">Ubicación</th>
                                <th class="text-center">Horas</th>
                                <th class="text-center">Extra</th>
                                <th class="text-center">Estado</th>
                                @if(!($esTrabajador ?? false))
                                <th style="min-width: 140px;" class="text-center">Acciones</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($fichajes as $fichaje)
                                <tr class="{{ $fichaje->fecha->isToday() ? 'table-info' : '' }} fichaje-row"
                                    data-id="{{ $fichaje->id }}"
                                    data-search="{{ strtolower($fichaje->trabajador->nombre ?? '') }} {{ strtolower($fichaje->trabajador->apellidos ?? '') }} {{ strtolower($fichaje->obra->nombre ?? '') }} {{ $fichaje->fecha->format('d/m/Y') }}">
                                    @if(!($esTrabajador ?? false))
                                    <td>
                                        @if(!$fichaje->validado)
                                            <input type="checkbox" class="form-check-input fichaje-checkbox"
                                                   name="fichajes[]" value="{{ $fichaje->id }}">
                                        @endif
                                    </td>
                                    @endif
                                    <td class="row-clickable" data-fichaje-id="{{ $fichaje->id }}">
                                        <strong>{{ $fichaje->fecha->format('d/m/Y') }}</strong>
                                        <br><small class="text-muted">{{ $fichaje->fecha->translatedFormat('l') }}</small>
                                        @if($fichaje->fecha->isToday())
                                            <span class="badge bg-info ms-1">Hoy</span>
                                        @endif
                                    </td>
                                    @if(!($esTrabajador ?? false))
                                    <td class="row-clickable" data-fichaje-id="{{ $fichaje->id }}">
                                        @can('ver_trabajadores')
                                            <a href="{{ route('trabajadores.show', $fichaje->trabajador) }}" class="text-decoration-none">
                                                {{ $fichaje->trabajador->nombre }} {{ $fichaje->trabajador->apellidos }}
                                            </a>
                                        @else
                                            {{ $fichaje->trabajador->nombre }} {{ $fichaje->trabajador->apellidos }}
                                        @endcan
                                    </td>
                                    @endif
                                    <td class="row-clickable" data-fichaje-id="{{ $fichaje->id }}">
                                        @if($fichaje->obra)
                                            @can('ver_obras')
                                                <a href="{{ route('obras.show', $fichaje->obra) }}" class="text-decoration-none">
                                                    {{ Str::limit($fichaje->obra->nombre, 25) }}
                                                </a>
                                            @else
                                                {{ Str::limit($fichaje->obra->nombre, 25) }}
                                            @endcan
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($fichaje->hora_entrada)
                                            <span class="badge bg-success bg-opacity-10 text-success">
                                                {{ \Carbon\Carbon::parse($fichaje->hora_entrada)->format('H:i') }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($fichaje->hora_salida)
                                            <span class="badge bg-danger bg-opacity-10 text-danger">
                                                {{ \Carbon\Carbon::parse($fichaje->hora_salida)->format('H:i') }}
                                            </span>
                                        @else
                                            <span class="badge bg-warning bg-opacity-10 text-warning">Pendiente</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($fichaje->latitud_entrada || $fichaje->latitud_salida)
                                            <div class="d-flex flex-column gap-1 align-items-center">
                                                @if($fichaje->latitud_entrada && $fichaje->longitud_entrada)
                                                    <a href="https://www.google.com/maps?q={{ $fichaje->latitud_entrada }},{{ $fichaje->longitud_entrada }}"
                                                       target="_blank"
                                                       class="btn btn-sm btn-outline-success py-0 px-2"
                                                       title="Ver entrada en Google Maps">
                                                        <i class="bi bi-geo-alt-fill me-1"></i>E
                                                    </a>
                                                @endif
                                                @if($fichaje->latitud_salida && $fichaje->longitud_salida)
                                                    <a href="https://www.google.com/maps?q={{ $fichaje->latitud_salida }},{{ $fichaje->longitud_salida }}"
                                                       target="_blank"
                                                       class="btn btn-sm btn-outline-danger py-0 px-2"
                                                       title="Ver salida en Google Maps">
                                                        <i class="bi bi-geo-alt-fill me-1"></i>S
                                                    </a>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($fichaje->horas_trabajadas)
                                            <strong>{{ number_format($fichaje->horas_trabajadas, 1) }}h</strong>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($fichaje->horas_extra > 0)
                                            <span class="badge bg-info">+{{ number_format($fichaje->horas_extra, 1) }}h</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($fichaje->validado)
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-lg me-1"></i>Validado
                                            </span>
                                        @else
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-clock me-1"></i>Pendiente
                                            </span>
                                        @endif
                                        @if($fichaje->corregido)
                                            <br><small class="text-muted"><i class="bi bi-pencil"></i> Corregido</small>
                                        @endif
                                    </td>
                                    @if(!($esTrabajador ?? false))
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-secondary btn-ver-detalle"
                                                    data-id="{{ $fichaje->id }}" title="Ver detalles">
                                                <i class="bi bi-eye"></i>
                                            </button>
                                            @can('editar_fichajes')
                                            <a href="{{ route('fichajes.edit', $fichaje) }}" class="btn btn-outline-primary" title="Editar">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            @endcan

                                            @if(!$fichaje->validado)
                                                @can('validar_fichajes')
                                                <button type="button" class="btn btn-outline-success btn-validar-individual"
                                                        data-id="{{ $fichaje->id }}" title="Validar">
                                                    <i class="bi bi-check-lg"></i>
                                                </button>
                                                @endcan
                                            @endif

                                            @can('eliminar_fichajes')
                                            <button type="button" class="btn btn-outline-danger"
                                                    onclick="confirmarEliminar({{ $fichaje->id }})" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                            @endcan
                                        </div>
                                    </td>
                                    @endif
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ ($esTrabajador ?? false) ? 8 : 11 }}" class="text-center py-4 text-muted">
                                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                        No hay fichajes para mostrar
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if(!($esTrabajador ?? false))
                    <div class="d-flex justify-content-between align-items-center p-3 border-top">
                        <div>
                            @can('validar_fichajes')
                            <button type="button" class="btn btn-success" id="btnValidarMultiple" disabled onclick="confirmarValidarMultiple()">
                                <i class="bi bi-check-all me-2"></i>Validar Seleccionados
                            </button>
                            @endcan
                        </div>
                        <div>
                            @if($fichajes->hasPages())
                                {{ $fichajes->withQueryString()->links() }}
                            @endif
                        </div>
                    </div>
                @else
                    @if($fichajes->hasPages())
                        <div class="p-3 border-top">
                            {{ $fichajes->withQueryString()->links() }}
                        </div>
                    @endif
                @endif
            @if(!($esTrabajador ?? false))
            </form>
            @endif
        </div>
    </div>
</div>

<!-- Delete Form (solo para admin) -->
@if(!($esTrabajador ?? false))
<form id="deleteForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<!-- Validar Individual Form -->
<form id="validarIndividualForm" method="POST" style="display: none;">
    @csrf
</form>
@endif

<!-- Modal Detalle Fichaje -->
<div class="modal fade" id="detalleModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-clock-history me-2"></i>Detalle del Fichaje
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detalleModalBody">
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <a href="#" class="btn btn-primary" id="btnEditarDesdeModal">
                    <i class="bi bi-pencil me-1"></i>Editar
                </a>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    @if(!($esTrabajador ?? false))
    // Select all checkboxes
    const selectAllEl = document.getElementById('selectAll');
    if (selectAllEl) {
        selectAllEl.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.fichaje-checkbox:not(:disabled)');
            checkboxes.forEach(cb => {
                const row = cb.closest('tr');
                if (row.style.display !== 'none') {
                    cb.checked = this.checked;
                }
            });
            updateValidarButton();
        });
    }

    // Update validar button state
    document.querySelectorAll('.fichaje-checkbox').forEach(cb => {
        cb.addEventListener('change', updateValidarButton);
    });

    function updateValidarButton() {
        const checked = document.querySelectorAll('.fichaje-checkbox:checked').length;
        const btn = document.getElementById('btnValidarMultiple');
        if (btn) {
            btn.disabled = checked === 0;
            btn.innerHTML = checked > 0
                ? '<i class="bi bi-check-all me-2"></i>Validar (' + checked + ')'
                : '<i class="bi bi-check-all me-2"></i>Validar Seleccionados';
        }
    }

    // Validar individual (botón en columna acciones)
    document.querySelectorAll('.btn-validar-individual').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            Swal.fire({
                title: '¿Validar fichaje?',
                text: 'Se marcará el fichaje como validado',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, validar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    showLoading();
                    const form = document.getElementById('validarIndividualForm');
                    form.action = '/fichajes/' + id + '/validar';
                    form.submit();
                }
            });
        });
    });

    // Ver detalle en modal
    document.querySelectorAll('.btn-ver-detalle').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            mostrarDetalleModal(id);
        });
    });
    @endif

    // Quick search
    const quickSearch = document.getElementById('quickSearch');
    if (quickSearch) {
        quickSearch.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.fichaje-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const searchData = row.dataset.search || '';
                if (searchTerm === '' || searchData.includes(searchTerm)) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            document.getElementById('visibleCount').textContent = visibleCount;
        });
    }

    // Loading on form submit
    document.getElementById('filterForm')?.addEventListener('submit', function() {
        showLoading();
    });
});

function showLoading() {
    document.getElementById('loadingOverlay').classList.add('active');
}

function hideLoading() {
    document.getElementById('loadingOverlay').classList.remove('active');
}

@if(!($esTrabajador ?? false))
// Delete confirmation
function confirmarEliminar(id) {
    Swal.fire({
        title: '¿Eliminar fichaje?',
        text: 'Esta acción no se puede deshacer',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            const form = document.getElementById('deleteForm');
            form.action = '/fichajes/' + id;
            form.submit();
        }
    });
}

// Confirmar validación múltiple
function confirmarValidarMultiple() {
    const checked = document.querySelectorAll('.fichaje-checkbox:checked').length;

    Swal.fire({
        title: '¿Validar fichajes seleccionados?',
        html: `Se van a validar <strong>${checked}</strong> fichaje(s).<br>Esta acción no se puede deshacer.`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, validar todos',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            showLoading();
            document.getElementById('validarMultipleForm').submit();
        }
    });
}

// Mostrar detalle en modal
function mostrarDetalleModal(id) {
    const modal = new bootstrap.Modal(document.getElementById('detalleModal'));
    const modalBody = document.getElementById('detalleModalBody');

    modalBody.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Cargando...</span>
            </div>
        </div>
    `;

    modal.show();

    fetch('/fichajes/' + id + '/details')
        .then(response => response.json())
        .then(data => {
            document.getElementById('btnEditarDesdeModal').href = '/fichajes/' + id + '/edit';

            modalBody.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th class="text-muted" width="40%">Fecha</th>
                                <td><strong>${data.fecha}</strong> (${data.dia})</td>
                            </tr>
                            <tr>
                                <th class="text-muted">Trabajador</th>
                                <td>${data.trabajador}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">Obra</th>
                                <td>${data.obra}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">Hora Entrada</th>
                                <td><span class="badge bg-success bg-opacity-10 text-success">${data.hora_entrada}</span></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Hora Salida</th>
                                <td>${data.hora_salida !== '-' ? '<span class="badge bg-danger bg-opacity-10 text-danger">' + data.hora_salida + '</span>' : '<span class="badge bg-warning">Pendiente</span>'}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm">
                            <tr>
                                <th class="text-muted" width="40%">Horas Trabajadas</th>
                                <td><strong>${data.horas_trabajadas}h</strong></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Horas Extra</th>
                                <td>${parseFloat(data.horas_extra) > 0 ? '<span class="badge bg-info">+' + data.horas_extra + 'h</span>' : '-'}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">Estado</th>
                                <td>${data.validado ? '<span class="badge bg-success">Validado</span>' : '<span class="badge bg-warning text-dark">Pendiente</span>'}</td>
                            </tr>
                            ${data.validado && data.validado_por ? `
                            <tr>
                                <th class="text-muted">Validado por</th>
                                <td>${data.validado_por}<br><small class="text-muted">${data.fecha_validacion}</small></td>
                            </tr>
                            ` : ''}
                            ${data.corregido ? `
                            <tr>
                                <th class="text-muted">Corregido por</th>
                                <td>${data.corregido_por || '-'}</td>
                            </tr>
                            <tr>
                                <th class="text-muted">Motivo corrección</th>
                                <td>${data.motivo_correccion || '-'}</td>
                            </tr>
                            ` : ''}
                        </table>
                    </div>
                </div>
                ${data.notas ? `
                <div class="mt-3">
                    <h6 class="text-muted">Notas</h6>
                    <p class="mb-0">${data.notas}</p>
                </div>
                ` : ''}
                ${data.ubicacion_entrada || data.ubicacion_salida ? `
                <div class="mt-3">
                    <h6 class="text-muted">Ubicaciones</h6>
                    <div class="d-flex gap-2">
                        ${data.ubicacion_entrada ? `
                        <a href="https://www.google.com/maps?q=${data.ubicacion_entrada.lat},${data.ubicacion_entrada.lng}"
                           target="_blank" class="btn btn-sm btn-outline-success">
                            <i class="bi bi-geo-alt-fill me-1"></i>Ver Entrada en Mapa
                        </a>
                        ` : ''}
                        ${data.ubicacion_salida ? `
                        <a href="https://www.google.com/maps?q=${data.ubicacion_salida.lat},${data.ubicacion_salida.lng}"
                           target="_blank" class="btn btn-sm btn-outline-danger">
                            <i class="bi bi-geo-alt-fill me-1"></i>Ver Salida en Mapa
                        </a>
                        ` : ''}
                    </div>
                </div>
                ` : ''}
                ${data.telefono_entrada || data.telefono_salida ? `
                <div class="mt-3">
                    <h6 class="text-muted">Teléfono asociado</h6>
                    <div class="d-flex gap-3 small">
                        ${data.telefono_entrada ? `<span><i class="bi bi-telephone-inbound me-1 text-success"></i>Entrada: <strong>${data.telefono_entrada}</strong></span>` : ''}
                        ${data.telefono_salida ? `<span><i class="bi bi-telephone-outbound me-1 text-danger"></i>Salida: <strong>${data.telefono_salida}</strong></span>` : ''}
                    </div>
                </div>
                ` : ''}
                ${data.ip_entrada || data.dispositivo_entrada || data.ip_salida || data.dispositivo_salida ? `
                <div class="mt-3">
                    <h6 class="text-muted">Dispositivo y red</h6>
                    <div class="small text-muted">
                        ${data.ip_entrada ? `<div><i class="bi bi-hdd-network me-1"></i>IP entrada: <strong>${data.ip_entrada}</strong>${data.precision_entrada ? ' · precisión ±' + Math.round(data.precision_entrada) + ' m' : ''}</div>` : ''}
                        ${data.dispositivo_entrada ? `<div class="text-truncate"><i class="bi bi-phone me-1"></i>${data.dispositivo_entrada}</div>` : ''}
                        ${data.ip_salida ? `<div class="mt-1"><i class="bi bi-hdd-network me-1"></i>IP salida: <strong>${data.ip_salida}</strong>${data.precision_salida ? ' · precisión ±' + Math.round(data.precision_salida) + ' m' : ''}</div>` : ''}
                    </div>
                </div>
                ` : ''}
            `;
        })
        .catch(error => {
            modalBody.innerHTML = `
                <div class="alert alert-danger mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Error al cargar los detalles del fichaje.
                </div>
            `;
        });
}
@endif

@if(($esTrabajador ?? false) && ($fichajeHoy ?? null))
// Botón fichar salida para trabajador
const btnSalida = document.getElementById('btnFicharSalida');
if (btnSalida) {
    btnSalida.addEventListener('click', function() {
        // Obtener GPS antes de fichar salida
        btnSalida.disabled = true;
        btnSalida.innerHTML = '<i class="bi bi-geo-alt me-2"></i>Obteniendo ubicación...';

        let lat = null, lng = null;

        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    enviarFichajeSalida(position.coords.latitude, position.coords.longitude, position.coords.accuracy);
                },
                function(error) {
                    // Ubicación OBLIGATORIA: no se ficha sin GPS.
                    Swal.fire({
                        icon: 'error',
                        title: 'Ubicación requerida',
                        text: 'Debes permitir la ubicación (GPS) para poder fichar la salida.',
                    });
                    btnSalida.disabled = false;
                    btnSalida.innerHTML = '<i class="bi bi-box-arrow-right me-2"></i>Fichar Salida';
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Sin geolocalización',
                text: 'Tu navegador no soporta geolocalización. No puedes fichar sin ubicación.',
            });
            btnSalida.disabled = false;
            btnSalida.innerHTML = '<i class="bi bi-box-arrow-right me-2"></i>Fichar Salida';
        }
    });
}

function enviarFichajeSalida(lat, lng, precision) {
    showLoading();
    fetch('{{ route("fichajes.check-out") }}', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            trabajador_id: {{ $trabajadorActual->id ?? 0 }},
            latitud: lat,
            longitud: lng,
            precision: precision
        })
    })
    .then(response => {
        if (response.status === 419) {
            hideLoading();
            Swal.fire({
                icon: 'warning',
                title: 'Sesión expirada',
                text: 'Tu sesión ha expirado. La página se recargará para que puedas intentarlo de nuevo.',
                confirmButtonText: 'Aceptar'
            }).then(() => {
                window.location.reload();
            });
            throw new Error('CSRF token expired');
        }
        if (response.status === 401 || response.redirected) {
            hideLoading();
            Swal.fire({
                icon: 'warning',
                title: 'Sesión expirada',
                text: 'Tu sesión ha expirado. Serás redirigido para iniciar sesión.',
                confirmButtonText: 'Aceptar'
            }).then(() => {
                window.location.reload();
            });
            throw new Error('Unauthenticated');
        }
        if (!response.ok) {
            throw new Error('Server error: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        hideLoading();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: '¡Salida registrada!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                window.location.reload();
            });
        } else {
            Swal.fire('Error', data.message, 'error');
            document.getElementById('btnFicharSalida').disabled = false;
            document.getElementById('btnFicharSalida').innerHTML = '<i class="bi bi-box-arrow-right me-2"></i>Fichar Salida';
        }
    })
    .catch(error => {
        hideLoading();
        if (error.message !== 'CSRF token expired' && error.message !== 'Unauthenticated') {
            Swal.fire('Error', 'Error al registrar la salida. Recarga la página e inténtalo de nuevo.', 'error');
            document.getElementById('btnFicharSalida').disabled = false;
            document.getElementById('btnFicharSalida').innerHTML = '<i class="bi bi-box-arrow-right me-2"></i>Fichar Salida';
        }
    });
}
@endif
</script>
@endpush
@endsection
