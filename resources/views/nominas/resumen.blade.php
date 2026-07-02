@extends('layouts.app')

@section('title', 'Resumen de Nóminas')

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Resumen de Nóminas</h1>
            <p class="text-muted mb-0">Coste de personal mensual — se refleja en gastos e impuestos</p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#modalCargaMasiva">
                <i class="bi bi-cloud-arrow-up me-2"></i>Carga masiva
            </button>
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalNomina">
                <i class="bi bi-plus-lg me-2"></i>Subir nómina
            </button>
        </div>
    </div>

    @if(session('import_ok'))
        <div class="alert alert-success alert-dismissible"><i class="bi bi-check-circle me-2"></i>{{ session('import_ok') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('import_error'))
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-triangle me-2"></i>{{ session('import_error') }}
            @if(session('import_errores'))
                <ul class="mb-0 mt-2 small">
                    @foreach(session('import_errores') as $err)
                        <li>Fila {{ $err['fila'] }} (DNI {{ $err['dni'] }}): {{ $err['motivo'] }}</li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" action="{{ route('nominas.resumen') }}" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Año</label>
                    <select name="anio" class="form-select" onchange="this.form.submit()">
                        @foreach($anios as $a)<option value="{{ $a }}" {{ $anio == $a ? 'selected' : '' }}>{{ $a }}</option>@endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Coste empresa (año)</small><h4 class="mb-0 text-danger">{{ number_format($totales['coste_empresa'], 2, ',', '.') }} €</h4><small class="text-muted">Bruto + SS empresa</small></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Seguridad Social total</small><h4 class="mb-0">{{ number_format($totales['ss_empresa'] + $totales['ss_trabajador'], 2, ',', '.') }} €</h4><small class="text-muted">Empresa + trabajador</small></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">IRPF total</small><h4 class="mb-0 text-primary">{{ number_format($totales['irpf'], 2, ',', '.') }} €</h4></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm h-100"><div class="card-body"><small class="text-muted">Líquido pagado</small><h4 class="mb-0">{{ number_format($totales['liquido'], 2, ',', '.') }} €</h4></div></div></div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white py-3"><h5 class="mb-0">Desglose mensual {{ $anio }}</h5></div>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr><th>Mes</th><th class="text-center">Nóminas</th><th class="text-end">Bruto</th><th class="text-end">SS empresa</th><th class="text-end">SS trabajador</th><th class="text-end">IRPF</th><th class="text-end">Líquido</th><th class="text-end">Coste empresa</th></tr>
                </thead>
                <tbody>
                    @forelse($porMes as $mesNum => $m)
                    <tr>
                        <td class="fw-medium"><a href="{{ route('nominas.mes', [$anio, $mesNum]) }}" class="text-decoration-none">{{ $m['nombre'] }} <i class="bi bi-box-arrow-up-right small"></i></a></td>
                        <td class="text-center"><a href="{{ route('nominas.mes', [$anio, $mesNum]) }}" class="badge bg-light text-dark text-decoration-none">{{ $m['count'] }}</a></td>
                        <td class="text-end">{{ number_format($m['bruto'], 2, ',', '.') }} €</td>
                        <td class="text-end">{{ number_format($m['ss_empresa'], 2, ',', '.') }} €</td>
                        <td class="text-end">{{ number_format($m['ss_trabajador'], 2, ',', '.') }} €</td>
                        <td class="text-end">{{ number_format($m['irpf'], 2, ',', '.') }} €</td>
                        <td class="text-end">{{ number_format($m['liquido'], 2, ',', '.') }} €</td>
                        <td class="text-end fw-bold text-danger">{{ number_format($m['coste_empresa'], 2, ',', '.') }} €</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">No hay nóminas registradas en {{ $anio }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="alert alert-info mt-3 small">
        <i class="bi bi-info-circle me-1"></i>La <strong>Seguridad Social</strong> y el <strong>IRPF</strong> de las nóminas se reflejan en el
        <a href="{{ route('impuestos.resumen') }}">Resumen de Impuestos</a>. El <strong>"coste empresa"</strong> es el gasto de personal de cada mes.
    </div>

    {{-- Modal: alta centralizada de nómina --}}
    <div class="modal fade" id="modalNomina" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <form method="POST" action="{{ route('nominas.store') }}" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Subir nómina</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        @if($errors->any())
                            <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
                        @endif
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Trabajador <span class="text-danger">*</span></label>
                                <select name="trabajador_id" class="form-select" required>
                                    <option value="">Selecciona un trabajador...</option>
                                    @foreach($trabajadores as $t)
                                        <option value="{{ $t->id }}" {{ old('trabajador_id') == $t->id ? 'selected' : '' }}>{{ $t->apellidos }}, {{ $t->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Mes <span class="text-danger">*</span></label>
                                <select name="mes" class="form-select" required>
                                    <option value="">Mes...</option>
                                    @foreach(\App\Models\Nomina::MESES as $num => $nombre)
                                        <option value="{{ $num }}" {{ old('mes') == $num ? 'selected' : '' }}>{{ $nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Año <span class="text-danger">*</span></label>
                                <select name="anio" class="form-select" required>
                                    @foreach($anios as $a)
                                        <option value="{{ $a }}" {{ old('anio', $anio) == $a ? 'selected' : '' }}>{{ $a }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Salario bruto <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" min="0" name="salario_bruto" id="n_bruto" class="form-control" value="{{ old('salario_bruto') }}" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">SS empresa</label>
                                <input type="number" step="0.01" min="0" name="ss_empresa" class="form-control" value="{{ old('ss_empresa') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">SS trabajador</label>
                                <input type="number" step="0.01" min="0" name="ss_trabajador" id="n_sstrab" class="form-control" value="{{ old('ss_trabajador') }}">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">IRPF</label>
                                <input type="number" step="0.01" min="0" name="irpf" id="n_irpf" class="form-control" value="{{ old('irpf') }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Líquido a percibir (automático)</label>
                                <input type="text" id="n_liquido" class="form-control bg-light fw-semibold" readonly value="0,00 €">
                                <small class="text-muted">Bruto − SS trabajador − IRPF</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Documento (PDF)</label>
                                <input type="file" name="documento" class="form-control" accept="application/pdf">
                                <small class="text-muted">Opcional. Máximo 5MB.</small>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Notas</label>
                                <textarea name="notas" class="form-control" rows="2">{{ old('notas') }}</textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-2"></i>Guardar nómina</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Modal: carga masiva de nóminas --}}
    @php $importaciones = \App\Models\NominaImportacion::with('user')->latest()->take(10)->get(); @endphp
    <div class="modal fade" id="modalCargaMasiva" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-cloud-arrow-up me-2"></i>Carga masiva de nóminas</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted">Crea las nóminas de todos los trabajadores en un solo paso desde un Excel. Si alguna fila tiene errores, <strong>no se importa nada</strong>.</p>

                    {{-- Paso 1: instrucciones + descargar plantilla --}}
                    <div class="row g-3 mb-4">
                        <div class="col-md-7">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <h6 class="mb-3"><i class="bi bi-info-circle me-2 text-primary"></i>Instrucciones</h6>
                                    <ol class="small mb-0 ps-3">
                                        <li>Descarga la plantilla con el mes y año elegidos.</li>
                                        <li>No modifiques las columnas <strong>DNI</strong>, <strong>Nombre</strong> ni <strong>Apellidos</strong> (son de referencia).</li>
                                        <li>Rellena <strong>Salario Bruto</strong>, <strong>SS Empresa</strong>, <strong>SS Trabajador</strong> e <strong>IRPF</strong>.</li>
                                        <li>El <strong>líquido</strong> se calcula solo (Bruto − SS Trabajador − IRPF).</li>
                                        <li>Los trabajadores que ya tengan nómina ese mes se <strong>omiten</strong> (no se pisan).</li>
                                        <li>Sube el archivo. Si hay errores, <strong>no se importa nada</strong>.</li>
                                    </ol>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-5">
                            <div class="card h-100 border">
                                <div class="card-body">
                                    <h6 class="mb-1"><i class="bi bi-file-earmark-excel me-2 text-success"></i>Plantilla</h6>
                                    <p class="small text-muted mb-3">Excel con una fila por trabajador activo.</p>
                                    <form method="GET" action="{{ route('nominas.plantilla') }}" class="row g-2 align-items-end">
                                        <div class="col-6">
                                            <label class="form-label small mb-1">Mes</label>
                                            <select name="mes" class="form-select form-select-sm" required>
                                                @foreach(\App\Models\Nomina::MESES as $num => $nombre)
                                                    <option value="{{ $num }}" {{ (int) now()->month === $num ? 'selected' : '' }}>{{ $nombre }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-6">
                                            <label class="form-label small mb-1">Año</label>
                                            <select name="anio" class="form-select form-select-sm" required>
                                                @foreach($anios as $a)
                                                    <option value="{{ $a }}" {{ $anio == $a ? 'selected' : '' }}>{{ $a }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="col-12 mt-3">
                                            <button type="submit" class="btn btn-success w-100"><i class="bi bi-download me-2"></i>Descargar plantilla</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Paso 2: subir archivo --}}
                    <div class="card border mb-4">
                        <div class="card-body">
                            <h6 class="mb-3"><i class="bi bi-cloud-arrow-up me-2 text-primary"></i>Subir archivo</h6>
                            <form method="POST" action="{{ route('nominas.importar') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                                @csrf
                                <div class="col-md-9">
                                    <input type="file" name="archivo" class="form-control" accept=".xlsx,.xls" required>
                                    <small class="text-muted">Solo Excel (.xlsx / .xls). Máximo 5MB.</small>
                                </div>
                                <div class="col-md-3">
                                    <button type="submit" class="btn btn-primary w-100"><i class="bi bi-lightning-charge me-2"></i>Procesar archivo</button>
                                </div>
                            </form>
                        </div>
                    </div>

                    {{-- Últimas importaciones (bitácora) --}}
                    <div class="card border">
                        <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                            <h6 class="mb-0"><i class="bi bi-clock-history me-2"></i>Últimas importaciones</h6>
                            <a href="{{ route('nominas.bitacora') }}" class="btn btn-sm btn-link text-decoration-none p-0">Ver bitácora completa <i class="bi bi-arrow-right"></i></a>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Archivo</th><th>Fecha</th><th>Usuario</th><th>Periodo</th>
                                        <th class="text-center">Creadas</th><th class="text-center">Omitidas</th>
                                        <th class="text-center">Errores</th><th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($importaciones as $imp)
                                        <tr>
                                            <td class="small">{{ $imp->archivo_nombre }}</td>
                                            <td class="small">{{ $imp->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="small">{{ $imp->user->name ?? '-' }}</td>
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
                                        </tr>
                                    @empty
                                        <tr><td colspan="8" class="text-center text-muted py-3">Aún no hay importaciones.</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    (function () {
        const bruto = document.getElementById('n_bruto');
        const ssTrab = document.getElementById('n_sstrab');
        const irpf = document.getElementById('n_irpf');
        const liquido = document.getElementById('n_liquido');
        function calc() {
            const v = (parseFloat(bruto.value) || 0) - (parseFloat(ssTrab.value) || 0) - (parseFloat(irpf.value) || 0);
            liquido.value = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(v);
        }
        [bruto, ssTrab, irpf].forEach(el => el && el.addEventListener('input', calc));
        @if($errors->any() || session('error'))
        document.addEventListener('DOMContentLoaded', function () {
            new bootstrap.Modal(document.getElementById('modalNomina')).show();
            calc();
        });
        @endif
        @if(session('import_ok') || session('import_error'))
        document.addEventListener('DOMContentLoaded', function () {
            new bootstrap.Modal(document.getElementById('modalCargaMasiva')).show();
        });
        @endif
    })();
</script>
@endpush
@endsection
