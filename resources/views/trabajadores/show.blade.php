@extends('layouts.app')

@section('title', $trabajador->nombre_completo)

@section('content')
<div class="container-fluid py-4">
    {{-- Page Header con Avatar Grande --}}
    <div class="worker-profile-header mb-4">
        <div class="d-flex align-items-center">
            <a href="{{ route('trabajadores.index') }}" class="btn btn-outline-secondary me-3">
                <i class="bi bi-arrow-left fs-5"></i>
            </a>
            @if($trabajador->hasProfilePhoto())
                <img src="{{ $trabajador->profile_photo_url }}"
                     alt="{{ $trabajador->nombre_completo }}"
                     class="worker-avatar"
                     style="object-fit: cover;">
            @else
                <div class="worker-avatar {{ auth()->user()->hasRole('Encargado') ? 'bg-secondary' : ($trabajador->tipo_relacion === 'propio' ? 'bg-primary' : 'bg-warning') }}">
                    {{ $trabajador->initials }}
                </div>
            @endif
            <div class="worker-info">
                <h1 class="worker-name">{{ $trabajador->nombre_completo }}</h1>
                <div class="worker-badges">
                    @unlessrole('Encargado')
                    @if($trabajador->tipo_relacion === 'propio')
                        <x-manzer.badge variant="primary">Propio</x-manzer.badge>
                    @else
                        <x-manzer.badge variant="warning">{{ $trabajador->subcontrata?->nombre ?? 'Subcontrata' }}</x-manzer.badge>
                    @endif
                    @endunlessrole
                    @if($trabajador->activo)
                        <x-manzer.badge variant="success">Activo</x-manzer.badge>
                    @else
                        <x-manzer.badge variant="danger">Baja</x-manzer.badge>
                    @endif
                    @if($trabajador->categoria_convenio)
                        <x-manzer.badge variant="secondary">{{ $trabajador->categoria_convenio }}</x-manzer.badge>
                    @endif
                </div>
            </div>
        </div>
        <div class="worker-actions">
            <x-manzer.button variant="outline-primary" icon="bi bi-pencil" onclick="editTrabajador({{ $trabajador->id }})">
                Editar
            </x-manzer.button>
            @if($trabajador->activo)
            <x-manzer.button variant="outline-danger" icon="bi bi-person-x" onclick="darBaja()">
                Dar de Baja
            </x-manzer.button>
            @endif
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="summary-cards">
        <x-manzer.stat-card
            icon="bi bi-calendar-check"
            :value="$trabajador->fecha_alta?->format('d/m/Y') ?? '-'"
            title="Fecha de Alta"
            color="primary"
        />
        <x-manzer.stat-card
            icon="bi bi-file-earmark-text"
            :value="$trabajador->documentos->count()"
            title="Documentos"
            color="info"
        />
        <x-manzer.stat-card
            icon="bi bi-mortarboard"
            :value="$trabajador->formaciones->count()"
            title="Formaciones"
            color="success"
        />
        <x-manzer.stat-card
            icon="bi bi-shield-check"
            :value="$trabajador->episEntregados->count()"
            title="EPIs Entregados"
            color="warning"
        />
    </div>

    {{-- Información principal en cards mejoradas --}}
    <div class="row g-4 mb-4">
        {{-- Datos Personales --}}
        <div class="col-lg-4">
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-icon bg-primary">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <h5 class="info-card-title">Datos Personales</h5>
                </div>
                <div class="info-card-body">
                    <div class="info-item">
                        <span class="info-label">DNI</span>
                        <span class="info-value fw-bold">{{ $trabajador->dni }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Email</span>
                        <span class="info-value">{{ $trabajador->email ?? '-' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Teléfono</span>
                        <span class="info-value">
                            @if($trabajador->telefono)
                                <a href="tel:{{ $trabajador->telefono }}" class="text-decoration-none">
                                    <i class="bi bi-telephone me-1"></i>{{ $trabajador->telefono }}
                                </a>
                            @else
                                -
                            @endif
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Dirección</span>
                        <span class="info-value">{{ $trabajador->direccion ?? '-' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Nacimiento</span>
                        <span class="info-value">{{ $trabajador->fecha_nacimiento?->format('d/m/Y') ?? '-' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">IBAN / Cuenta</span>
                        <span class="info-value">{{ $trabajador->iban ?? '-' }}</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Datos Laborales --}}
        <div class="col-lg-4">
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-icon bg-success">
                        <i class="bi bi-briefcase-fill"></i>
                    </div>
                    <h5 class="info-card-title">Datos Laborales</h5>
                </div>
                <div class="info-card-body">
                    <div class="info-item">
                        <span class="info-label">Fecha Alta</span>
                        <span class="info-value fw-bold">{{ $trabajador->fecha_alta?->format('d/m/Y') }}</span>
                    </div>
                    @if($trabajador->fecha_baja)
                    <div class="info-item">
                        <span class="info-label">Fecha Baja</span>
                        <span class="info-value text-danger fw-bold">{{ $trabajador->fecha_baja->format('d/m/Y') }}</span>
                    </div>
                    @endif
                    <div class="info-item">
                        <span class="info-label">Antigüedad</span>
                        <span class="info-value">{{ $trabajador->antiguedad?->format('d/m/Y') ?? '-' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Cuadrilla</span>
                        <span class="info-value">
                            @php $cuadrillaActual = $trabajador->cuadrillas->where('pivot.activo', true)->first(); @endphp
                            @if($cuadrillaActual)
                                <a href="{{ route('cuadrillas.show', $cuadrillaActual) }}" class="text-decoration-none">
                                    <i class="bi bi-people me-1"></i>{{ $cuadrillaActual->nombre }}
                                </a>
                            @else
                                <span class="text-muted">Sin asignar</span>
                            @endif
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Vacaciones</span>
                        <span class="info-value">
                            <x-manzer.progress-bar
                                :percentage="round(($trabajador->vacaciones_acumuladas ?? 0) / ($trabajador->vacaciones_anuales ?? 22) * 100)"
                                color="info"
                                :show-label="false"
                            />
                            <small class="text-muted d-block mt-1">
                                {{ $trabajador->vacaciones_acumuladas ?? 0 }} / {{ $trabajador->vacaciones_anuales ?? 22 }} días
                            </small>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        @unlessrole('Encargado')
        {{-- Datos Económicos o Subcontrata --}}
        <div class="col-lg-4">
            @if($trabajador->tipo_relacion === 'propio')
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-icon bg-warning">
                        <i class="bi bi-cash-coin"></i>
                    </div>
                    <h5 class="info-card-title">Datos Económicos</h5>
                </div>
                <div class="info-card-body">
                    <div class="info-item">
                        <span class="info-label">Salario Bruto</span>
                        <span class="info-value fw-bold text-success">
                            {{ number_format($trabajador->salario_bruto_mensual ?? 0, 2, ',', '.') }} €/mes
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Coste/Día</span>
                        <span class="info-value">{{ number_format($trabajador->coste_empresa_dia ?? 0, 2, ',', '.') }} €</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Coste/Hora</span>
                        <span class="info-value">{{ number_format($trabajador->coste_hora ?? 0, 2, ',', '.') }} €</span>
                    </div>
                </div>
            </div>
            @else
            <div class="info-card">
                <div class="info-card-header">
                    <div class="info-card-icon bg-info">
                        <i class="bi bi-building"></i>
                    </div>
                    <h5 class="info-card-title">Subcontrata</h5>
                </div>
                <div class="info-card-body">
                    @if($trabajador->subcontrata)
                    <div class="info-item">
                        <span class="info-label">Empresa</span>
                        <span class="info-value fw-bold">{{ $trabajador->subcontrata->nombre }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">CIF</span>
                        <span class="info-value">{{ $trabajador->subcontrata->cif ?? '-' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Contacto</span>
                        <span class="info-value">{{ $trabajador->subcontrata->persona_contacto ?? '-' }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">Tarifa/Día</span>
                        <span class="info-value">{{ number_format($trabajador->subcontrata->tarifa_dia ?? 0, 2, ',', '.') }} €</span>
                    </div>
                    @else
                    <p class="text-muted mb-0 text-center py-3">
                        <i class="bi bi-building fs-3 d-block mb-2 opacity-50"></i>
                        Sin subcontrata asignada
                    </p>
                    @endif
                </div>
            </div>
            @endif
        </div>
        @endunlessrole
    </div>

    {{-- Tabs de contenido mejorados --}}
    <div class="content-tabs-card">
        <div class="tabs-header">
            <ul class="nav nav-pills" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#documentos" type="button">
                        <i class="bi bi-file-earmark-text fs-5"></i>
                        <span>Documentos</span>
                        <span class="tab-badge">{{ $trabajador->documentos->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#formaciones" type="button">
                        <i class="bi bi-mortarboard fs-5"></i>
                        <span>Formaciones</span>
                        <span class="tab-badge">{{ $trabajador->formaciones->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#epis" type="button">
                        <i class="bi bi-shield-check fs-5"></i>
                        <span>EPIs</span>
                        <span class="tab-badge">{{ $trabajador->episEntregados->count() }}</span>
                    </button>
                </li>
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#historial" type="button">
                        <i class="bi bi-exclamation-triangle fs-5"></i>
                        <span>Historial</span>
                        <span class="tab-badge {{ $trabajador->historialDisciplinario->count() > 0 ? 'bg-danger' : '' }}">
                            {{ $trabajador->historialDisciplinario->count() }}
                        </span>
                    </button>
                </li>
                @role('Administrador|RRHH|Contabilidad')
                <li class="nav-item">
                    <button class="nav-link" data-bs-toggle="tab" data-bs-target="#nominas" type="button">
                        <i class="bi bi-cash-stack fs-5"></i>
                        <span>Nóminas</span>
                        <span class="tab-badge">{{ $trabajador->nominas->count() }}</span>
                    </button>
                </li>
                @endrole
            </ul>
        </div>

        <div class="tabs-content">
            <div class="tab-content" id="tabsContent">
                @role('Administrador|RRHH|Contabilidad')
                {{-- Tab Nóminas --}}
                <div class="tab-pane" id="nominas" role="tabpanel">
                    <div class="tab-header">
                        <h5><i class="bi bi-cash-stack me-2"></i>Nóminas del Trabajador</h5>
                    </div>

                    @if(session('success'))<div class="alert alert-success alert-dismissible fade show">{{ session('success') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif
                    @if(session('error'))<div class="alert alert-danger alert-dismissible fade show">{{ session('error') }}<button class="btn-close" data-bs-dismiss="alert"></button></div>@endif

                    {{-- Alta de nómina --}}
                    <form action="{{ route('trabajadores.nominas.store', $trabajador) }}" method="POST" enctype="multipart/form-data" class="border rounded p-3 mb-3 bg-light">
                        @csrf
                        <div class="row g-2">
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Año</label>
                                <input type="number" name="anio" class="form-control form-control-sm" value="{{ date('Y') }}" min="2000" max="2100" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Mes</label>
                                <select name="mes" class="form-select form-select-sm" required>
                                    @foreach(\App\Models\Nomina::MESES as $num => $nom)
                                        <option value="{{ $num }}" {{ $num == date('n') ? 'selected' : '' }}>{{ $nom }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Salario bruto</label>
                                <input type="number" step="0.01" min="0" name="salario_bruto" class="form-control form-control-sm" required>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">SS empresa</label>
                                <input type="number" step="0.01" min="0" name="ss_empresa" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">SS trabajador</label>
                                <input type="number" step="0.01" min="0" name="ss_trabajador" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">IRPF</label>
                                <input type="number" step="0.01" min="0" name="irpf" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-8">
                                <label class="form-label small mb-1">PDF de la nómina (opcional)</label>
                                <input type="file" name="documento" accept="application/pdf" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary btn-sm w-100"><i class="bi bi-plus-lg me-1"></i>Añadir nómina</button>
                            </div>
                        </div>
                        <small class="text-muted d-block mt-2">Líquido = bruto − SS trabajador − IRPF (se calcula solo). Coste empresa = bruto + SS empresa.</small>
                    </form>

                    @if($trabajador->nominas->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr><th>Periodo</th><th class="text-end">Bruto</th><th class="text-end">SS empresa</th><th class="text-end">IRPF</th><th class="text-end">Líquido</th><th class="text-end">Coste empresa</th><th class="text-center">PDF</th><th class="text-end">Acciones</th></tr>
                            </thead>
                            <tbody>
                                @foreach($trabajador->nominas as $nom)
                                <tr>
                                    <td class="fw-medium">{{ $nom->mes_nombre }} {{ $nom->anio }}</td>
                                    <td class="text-end">{{ number_format($nom->salario_bruto, 2, ',', '.') }} €</td>
                                    <td class="text-end">{{ number_format($nom->ss_empresa, 2, ',', '.') }} €</td>
                                    <td class="text-end">{{ number_format($nom->irpf, 2, ',', '.') }} €</td>
                                    <td class="text-end fw-bold">{{ number_format($nom->liquido, 2, ',', '.') }} €</td>
                                    <td class="text-end">{{ number_format($nom->coste_empresa, 2, ',', '.') }} €</td>
                                    <td class="text-center text-nowrap">
                                        <a href="{{ route('nominas.recibo', $nom) }}" target="_blank" class="btn btn-sm btn-outline-success" title="Ver recibo PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                                        @if($nom->documento_path)
                                            <a href="{{ route('nominas.download', $nom) }}" class="btn btn-sm btn-outline-primary" title="Descargar PDF adjunto"><i class="bi bi-download"></i></a>
                                        @endif
                                    </td>
                                    <td class="text-end text-nowrap">
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-editar-nomina"
                                            title="Editar"
                                            data-action="{{ route('nominas.update', $nom) }}"
                                            data-nombre="{{ $nom->mes_nombre }} {{ $nom->anio }} — {{ $trabajador->nombre_completo }}"
                                            data-bruto="{{ $nom->salario_bruto }}"
                                            data-ssempresa="{{ $nom->ss_empresa }}"
                                            data-sstrab="{{ $nom->ss_trabajador }}"
                                            data-irpf="{{ $nom->irpf }}"
                                            data-notas="{{ $nom->notas }}">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <form action="{{ route('nominas.destroy', $nom) }}" method="POST" onsubmit="return confirm('¿Eliminar esta nómina?');" class="d-inline">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="empty-tab-state">
                        <i class="bi bi-cash-stack"></i>
                        <p>No hay nóminas registradas</p>
                    </div>
                    @endif
                </div>
                @endrole

                {{-- Tab Documentos --}}
                <div class="tab-pane active" id="documentos" role="tabpanel">
                    <div class="tab-header">
                        <h5><i class="bi bi-file-earmark-text me-2"></i>Documentos del Trabajador</h5>
                        <x-manzer.button variant="primary" icon="bi bi-plus-lg" size="sm" data-bs-toggle="modal" data-bs-target="#addDocumentoModal">
                            Añadir Documento
                        </x-manzer.button>
                    </div>

                    @if($trabajador->documentos->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tipo</th>
                                    <th>Nombre</th>
                                    <th>Fecha</th>
                                    <th>Caducidad</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($trabajador->documentos as $documento)
                                <tr>
                                    <td><x-manzer.badge variant="secondary">{{ ucfirst($documento->tipo) }}</x-manzer.badge></td>
                                    <td class="fw-medium">{{ $documento->nombre }}</td>
                                    <td>{{ $documento->fecha_documento?->format('d/m/Y') ?? '-' }}</td>
                                    <td>{{ $documento->fecha_caducidad?->format('d/m/Y') ?? 'Sin caducidad' }}</td>
                                    <td>
                                        @if($documento->caducado)
                                            <x-manzer.badge variant="danger">Caducado</x-manzer.badge>
                                        @elseif($documento->proximo_a_caducar)
                                            <x-manzer.badge variant="warning">Próximo a caducar</x-manzer.badge>
                                        @else
                                            <x-manzer.badge variant="success">Vigente</x-manzer.badge>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            <a href="{{ asset($documento->archivo_path) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Descargar">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteDocumento({{ $documento->id }}, '{{ $documento->nombre }}')" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="empty-tab-state">
                        <i class="bi bi-file-earmark-text"></i>
                        <h5>No hay documentos</h5>
                        <p>Aún no se han subido documentos para este trabajador.</p>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addDocumentoModal">
                            <i class="bi bi-plus-lg me-1"></i>Subir primer documento
                        </button>
                    </div>
                    @endif
                </div>

                {{-- Tab Formaciones --}}
                <div class="tab-pane" id="formaciones" role="tabpanel">
                    <div class="tab-header">
                        <h5><i class="bi bi-mortarboard me-2"></i>Formaciones del Trabajador</h5>
                        <x-manzer.button variant="primary" icon="bi bi-plus-lg" size="sm" data-bs-toggle="modal" data-bs-target="#addFormacionModal">
                            Añadir Formación
                        </x-manzer.button>
                    </div>

                    @if($trabajador->formaciones->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Formación</th>
                                    <th>Centro</th>
                                    <th>Realización</th>
                                    <th>Caducidad</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($trabajador->formaciones as $formacion)
                                <tr>
                                    <td>
                                        <strong>{{ $formacion->tipo?->nombre ?? 'Sin tipo' }}</strong>
                                        @if($formacion->notas)
                                        <br><small class="text-muted">{{ Str::limit($formacion->notas, 50) }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $formacion->centro_formacion ?? '-' }}</td>
                                    <td>{{ $formacion->fecha_realizacion?->format('d/m/Y') }}</td>
                                    <td>{{ $formacion->fecha_caducidad?->format('d/m/Y') ?? 'Sin caducidad' }}</td>
                                    <td>
                                        @if($formacion->caducado)
                                            <x-manzer.badge variant="danger">Caducada</x-manzer.badge>
                                        @elseif($formacion->proximo_a_caducar)
                                            <x-manzer.badge variant="warning">Próxima a caducar</x-manzer.badge>
                                        @else
                                            <x-manzer.badge variant="success">Vigente</x-manzer.badge>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group">
                                            @if($formacion->certificado_path)
                                            <a href="{{ asset($formacion->certificado_path) }}" target="_blank" class="btn btn-sm btn-outline-primary" title="Descargar certificado">
                                                <i class="bi bi-download"></i>
                                            </a>
                                            @endif
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="deleteFormacion({{ $formacion->id }}, '{{ $formacion->tipo?->nombre }}')" title="Eliminar">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="empty-tab-state">
                        <i class="bi bi-mortarboard"></i>
                        <h5>No hay formaciones</h5>
                        <p>Aún no se han registrado formaciones para este trabajador.</p>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addFormacionModal">
                            <i class="bi bi-plus-lg me-1"></i>Añadir primera formación
                        </button>
                    </div>
                    @endif
                </div>

                {{-- Tab EPIs --}}
                <div class="tab-pane" id="epis" role="tabpanel">
                    <div class="tab-header">
                        <h5><i class="bi bi-shield-check me-2"></i>EPIs Entregados</h5>
                        @role('Administrador|RRHH')
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#entregarEpiModal">
                            <i class="bi bi-plus-lg me-1"></i>Entregar EPI
                        </button>
                        @endrole
                    </div>

                    @php
                        $episActivos = $trabajador->episEntregados->whereNull('fecha_devolucion');
                        $episDevueltos = $trabajador->episEntregados->whereNotNull('fecha_devolucion');
                    @endphp

                    @if($episActivos->count() > 0)
                    <h6 class="text-muted mb-3"><i class="bi bi-check-circle me-1"></i>EPIs Actualmente Asignados ({{ $episActivos->count() }})</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>EPI</th>
                                    <th>Categoria</th>
                                    <th>Fecha Entrega</th>
                                    <th>Caducidad</th>
                                    <th class="text-center">Firma</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($episActivos as $entrega)
                                <tr class="{{ $entrega->inventario?->estaCaducado() ? 'table-danger' : ($entrega->inventario?->proximoACaducar() ? 'table-warning' : '') }}">
                                    <td>
                                        <a href="{{ route('epi-inventario.show', $entrega->epi_inventario_id) }}" class="text-decoration-none fw-semibold">
                                            {{ $entrega->inventario?->catalogo?->nombre ?? $entrega->nombre_epi }}
                                        </a>
                                        @if($entrega->inventario?->numero_serie)
                                        <br><small class="text-muted">S/N: {{ $entrega->inventario->numero_serie }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $entrega->inventario?->catalogo?->categoria ?? $entrega->categoria_epi ?? '-' }}</td>
                                    <td>{{ $entrega->fecha_entrega?->format('d/m/Y') }}</td>
                                    <td>
                                        @if($entrega->inventario?->fecha_caducidad)
                                            @if($entrega->inventario->estaCaducado())
                                                <span class="text-danger fw-bold">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                                    {{ $entrega->inventario->fecha_caducidad->format('d/m/Y') }}
                                                </span>
                                            @elseif($entrega->inventario->proximoACaducar())
                                                <span class="text-warning">
                                                    <i class="bi bi-clock me-1"></i>
                                                    {{ $entrega->inventario->fecha_caducidad->format('d/m/Y') }}
                                                </span>
                                            @else
                                                {{ $entrega->inventario->fecha_caducidad->format('d/m/Y') }}
                                            @endif
                                        @else
                                            <span class="text-muted">Sin caducidad</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        @if($entrega->firma_trabajador_path)
                                        <button type="button" class="btn btn-sm btn-outline-secondary"
                                                onclick="verFirmaEpi('{{ asset($entrega->firma_trabajador_path) }}')">
                                            <i class="bi bi-pen"></i>
                                        </button>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <a href="{{ route('epi-inventario.show', $entrega->epi_inventario_id) }}" class="btn btn-sm btn-outline-info" title="Ver EPI">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        @role('Administrador|RRHH')
                                        <button type="button" class="btn btn-sm btn-outline-warning"
                                                onclick="abrirModalDevolver({{ $entrega->epi_inventario_id }})"
                                                title="Devolver">
                                            <i class="bi bi-box-arrow-left"></i>
                                        </button>
                                        @endrole
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    @if($episDevueltos->count() > 0)
                    <h6 class="text-muted mb-3"><i class="bi bi-clock-history me-1"></i>Historial de EPIs Devueltos ({{ $episDevueltos->count() }})</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>EPI</th>
                                    <th>Categoria</th>
                                    <th>Fecha Entrega</th>
                                    <th>Fecha Devolucion</th>
                                    <th>Motivo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($episDevueltos->take(10) as $entrega)
                                <tr class="table-light">
                                    <td>
                                        <a href="{{ route('epi-inventario.show', $entrega->epi_inventario_id) }}" class="text-decoration-none">
                                            {{ $entrega->inventario?->catalogo?->nombre ?? $entrega->nombre_epi }}
                                        </a>
                                        @if($entrega->inventario?->numero_serie)
                                        <br><small class="text-muted">S/N: {{ $entrega->inventario->numero_serie }}</small>
                                        @endif
                                    </td>
                                    <td>{{ $entrega->inventario?->catalogo?->categoria ?? $entrega->categoria_epi ?? '-' }}</td>
                                    <td>{{ $entrega->fecha_entrega?->format('d/m/Y') }}</td>
                                    <td>{{ $entrega->fecha_devolucion?->format('d/m/Y') }}</td>
                                    <td>{{ $entrega->motivo_devolucion ?? '-' }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                        @if($episDevueltos->count() > 10)
                        <div class="text-center mt-2">
                            <small class="text-muted">Mostrando 10 de {{ $episDevueltos->count() }} entregas anteriores</small>
                        </div>
                        @endif
                    </div>
                    @endif

                    @if($trabajador->episEntregados->count() == 0)
                    <div class="empty-tab-state">
                        <i class="bi bi-shield-check text-success"></i>
                        <h5>No hay EPIs entregados</h5>
                        <p>Este trabajador no tiene EPIs asignados actualmente.</p>
                        @role('Administrador|RRHH')
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#entregarEpiModal">
                            <i class="bi bi-plus-lg me-1"></i>Entregar primer EPI
                        </button>
                        @endrole
                    </div>
                    @endif
                </div>

                {{-- Tab Historial Disciplinario --}}
                <div class="tab-pane" id="historial" role="tabpanel">
                    <div class="tab-header">
                        <h5><i class="bi bi-exclamation-triangle me-2"></i>Historial Disciplinario</h5>
                        <x-manzer.button variant="warning" icon="bi bi-plus-lg" size="sm" data-bs-toggle="modal" data-bs-target="#addHistorialModal">
                            Registrar Incidencia
                        </x-manzer.button>
                    </div>

                    @if($trabajador->historialDisciplinario->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Tipo</th>
                                    <th>Descripción</th>
                                    <th class="text-end">Documento</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $tipoLabels = [
                                        'amonestacion_verbal' => 'Amonestación Verbal',
                                        'amonestacion_escrita' => 'Amonestación Escrita',
                                        'sancion_leve' => 'Sanción Leve',
                                        'sancion_grave' => 'Sanción Grave',
                                        'sancion_muy_grave' => 'Sanción Muy Grave',
                                    ];
                                    $tipoVariants = [
                                        'amonestacion_verbal' => 'info',
                                        'amonestacion_escrita' => 'warning',
                                        'sancion_leve' => 'warning',
                                        'sancion_grave' => 'danger',
                                        'sancion_muy_grave' => 'danger',
                                    ];
                                @endphp
                                @foreach($trabajador->historialDisciplinario->sortByDesc('fecha') as $incidencia)
                                <tr>
                                    <td class="fw-medium">{{ $incidencia->fecha?->format('d/m/Y') }}</td>
                                    <td>
                                        <x-manzer.badge :variant="$tipoVariants[$incidencia->tipo] ?? 'secondary'">
                                            {{ $tipoLabels[$incidencia->tipo] ?? $incidencia->tipo }}
                                        </x-manzer.badge>
                                    </td>
                                    <td>{{ Str::limit($incidencia->descripcion, 100) }}</td>
                                    <td class="text-end">
                                        @if($incidencia->documento_path)
                                        <a href="{{ asset($incidencia->documento_path) }}" target="_blank" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        @else
                                        -
                                        @endif
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <div class="empty-tab-state success">
                        <i class="bi bi-check-circle text-success"></i>
                        <h5>Sin incidencias</h5>
                        <p>Este trabajador no tiene incidencias registradas.</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal Añadir Documento --}}
<div class="modal fade" id="addDocumentoModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('trabajadores.documentos.store', $trabajador) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Añadir Documento</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <x-manzer.form-group label="Tipo de Documento" name="tipo" type="select" required>
                        <option value="">Seleccionar...</option>
                        <option value="contrato">Contrato</option>
                        <option value="dni">DNI</option>
                        <option value="nomina">Nómina</option>
                        <option value="certificado_medico">Certificado Médico</option>
                        <option value="formacion">Certificado Formación</option>
                        <option value="otro">Otro</option>
                    </x-manzer.form-group>

                    <x-manzer.form-group label="Nombre del Documento" name="nombre" type="text" required />

                    <x-manzer.form-group label="Archivo" name="archivo" type="file" help="Máximo 10MB." required />

                    <div class="row">
                        <div class="col-md-6">
                            <x-manzer.form-group label="Fecha Documento" name="fecha_documento" type="date" :value="date('Y-m-d')" />
                        </div>
                        <div class="col-md-6">
                            <x-manzer.form-group label="Fecha Caducidad" name="fecha_caducidad" type="date" />
                        </div>
                    </div>

                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="visible_trabajador" id="visibleTrabajador" value="1" checked>
                        <label class="form-check-label" for="visibleTrabajador">Visible para el trabajador</label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="requiere_lectura" id="requiereLectura" value="1">
                        <label class="form-check-label" for="requiereLectura">Requiere lectura certificada</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-upload me-1"></i>Subir Documento</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Añadir Formación --}}
<div class="modal fade" id="addFormacionModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('trabajadores.formaciones.store', $trabajador) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Añadir Formación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <x-manzer.form-group
                        label="Tipo de Formación"
                        name="formacion_tipo_id"
                        type="select"
                        required
                    >
                        <option value="">Seleccionar...</option>
                        @foreach($formacionTipos as $tipo)
                        <option value="{{ $tipo->id }}">{{ $tipo->nombre }}</option>
                        @endforeach
                    </x-manzer.form-group>

                    <x-manzer.form-group
                        label="Centro de Formación"
                        name="centro_formacion"
                        type="text"
                    />

                    <div class="row">
                        <div class="col-md-6">
                            <x-manzer.form-group
                                label="Fecha Realización"
                                name="fecha_realizacion"
                                type="date"
                                required
                            />
                        </div>
                        <div class="col-md-6">
                            <x-manzer.form-group
                                label="Fecha Caducidad"
                                name="fecha_caducidad"
                                type="date"
                            />
                        </div>
                    </div>

                    <x-manzer.form-group
                        label="Certificado"
                        name="certificado"
                        type="file"
                        help="Máximo 10MB."
                    />

                    <x-manzer.form-group
                        label="Notas"
                        name="notas"
                        type="textarea"
                        :rows="2"
                    />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Guardar Formación</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Añadir Incidencia --}}
<div class="modal fade" id="addHistorialModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('trabajadores.historial.store', $trabajador) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Incidencia</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <x-manzer.form-group
                                label="Tipo de Incidencia"
                                name="tipo"
                                type="select"
                                required
                            >
                                <option value="">Seleccionar...</option>
                                <option value="amonestacion_verbal">Amonestación Verbal</option>
                                <option value="amonestacion_escrita">Amonestación Escrita</option>
                                <option value="sancion_leve">Sanción Leve</option>
                                <option value="sancion_grave">Sanción Grave</option>
                                <option value="sancion_muy_grave">Sanción Muy Grave</option>
                            </x-manzer.form-group>
                        </div>
                        <div class="col-md-6">
                            <x-manzer.form-group
                                label="Fecha"
                                name="fecha"
                                type="date"
                                :value="date('Y-m-d')"
                                required
                            />
                        </div>
                    </div>

                    <x-manzer.form-group
                        label="Descripción"
                        name="descripcion"
                        type="textarea"
                        :rows="3"
                        required
                    />

                    <x-manzer.form-group
                        label="Documento Adjunto"
                        name="documento"
                        type="file"
                    />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-exclamation-triangle me-1"></i>Registrar Incidencia</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Dar de Baja --}}
<div class="modal fade" id="bajaModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="{{ route('trabajadores.baja', $trabajador) }}" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Dar de Baja al Trabajador</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <x-manzer.alert type="warning" message="Esta acción desactivará al trabajador y lo eliminará de todas las cuadrillas activas." />

                    <x-manzer.form-group
                        label="Fecha de Baja"
                        name="fecha_baja"
                        type="date"
                        :value="date('Y-m-d')"
                        :min="$trabajador->fecha_alta?->format('Y-m-d')"
                        required
                    />
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-person-x me-1"></i>Confirmar Baja</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Forms para eliminar --}}
<form id="deleteDocumentoForm" method="POST" class="d-none">
    @csrf
    @method('DELETE')
</form>

<form id="deleteFormacionForm" method="POST" class="d-none">
    @csrf
    @method('DELETE')
</form>

{{-- Modal Entregar EPI --}}
@role('Administrador|RRHH')
<div class="modal fade" id="entregarEpiModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="entregarEpiForm" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-shield-check me-2"></i>Entregar EPI a {{ $trabajador->nombre_completo }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="trabajador_id" value="{{ $trabajador->id }}">

                    <div class="mb-3">
                        <label class="form-label">Seleccionar EPI Disponible <span class="text-danger">*</span></label>
                        <select name="epi_inventario_id" id="selectEpiInventario" class="form-select" required>
                            <option value="">Cargando EPIs disponibles...</option>
                        </select>
                        <small class="text-muted">Solo se muestran EPIs con estado "disponible"</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="2" placeholder="Notas adicionales sobre la entrega..."></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Firma del Trabajador</label>
                        <div class="border rounded p-2 bg-light">
                            <canvas id="signatureCanvasEntrega" class="border bg-white" style="width: 100%; height: 150px;"></canvas>
                        </div>
                        <input type="hidden" name="firma_trabajador" id="firmaInputEntrega">
                        <div class="mt-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="limpiarFirmaEntrega()">
                                <i class="bi bi-eraser me-1"></i>Limpiar Firma
                            </button>
                        </div>
                        <small class="text-muted">El trabajador debe firmar para confirmar la recepcion del EPI</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Confirmar Entrega</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Devolver EPI --}}
<div class="modal fade" id="devolverEpiModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="devolverEpiForm" method="POST">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-box-arrow-left me-2"></i>Devolver EPI</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Motivo de Devolucion <span class="text-danger">*</span></label>
                        <select name="motivo_devolucion" class="form-select" required>
                            <option value="">Seleccionar...</option>
                            <option value="Fin de uso">Fin de uso</option>
                            <option value="Deterioro">Deterioro</option>
                            <option value="Cambio de talla">Cambio de talla</option>
                            <option value="Caducidad">Caducidad</option>
                            <option value="Baja del trabajador">Baja del trabajador</option>
                            <option value="Otro">Otro</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Nuevo Estado del EPI</label>
                        <select name="nuevo_estado" class="form-select">
                            <option value="disponible">Disponible (puede reutilizarse)</option>
                            <option value="en_revision">En Revision (necesita verificacion)</option>
                            <option value="baja">Baja (no utilizable)</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Observaciones</label>
                        <textarea name="observaciones" class="form-control" rows="2" placeholder="Comentarios adicionales..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-box-arrow-left me-1"></i>Confirmar Devolucion</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal Ver Firma EPI --}}
<div class="modal fade" id="firmaEpiModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pen me-2"></i>Firma del Trabajador</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="firmaEpiImage" src="" alt="Firma" class="img-fluid border">
            </div>
        </div>
    </div>
</div>
@endrole

{{-- Modal: editar nómina (aditivo) --}}
@role('Administrador|RRHH|Contabilidad')
<div class="modal fade" id="modalEditarNomina" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" id="formEditarNomina">
                @csrf
                @method('PUT')
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Editar nómina</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3" id="edit_nombre"></p>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Salario bruto <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" min="0" name="salario_bruto" id="edit_bruto" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SS empresa</label>
                            <input type="number" step="0.01" min="0" name="ss_empresa" id="edit_ssempresa" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SS trabajador</label>
                            <input type="number" step="0.01" min="0" name="ss_trabajador" id="edit_sstrab" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IRPF</label>
                            <input type="number" step="0.01" min="0" name="irpf" id="edit_irpf" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Líquido a percibir (automático)</label>
                            <input type="text" id="edit_liquido" class="form-control bg-light fw-semibold" readonly>
                            <small class="text-muted">Bruto − SS trabajador − IRPF</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea name="notas" id="edit_notas" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-2"></i>Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endrole

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('formEditarNomina');
        if (!form) return;
        const bruto = document.getElementById('edit_bruto');
        const ssTrab = document.getElementById('edit_sstrab');
        const irpf = document.getElementById('edit_irpf');
        const liquido = document.getElementById('edit_liquido');
        function calc() {
            const v = (parseFloat(bruto.value) || 0) - (parseFloat(ssTrab.value) || 0) - (parseFloat(irpf.value) || 0);
            liquido.value = new Intl.NumberFormat('es-ES', { style: 'currency', currency: 'EUR' }).format(v);
        }
        [bruto, ssTrab, irpf].forEach(el => el && el.addEventListener('input', calc));
        document.querySelectorAll('.btn-editar-nomina').forEach(function (btn) {
            btn.addEventListener('click', function () {
                form.setAttribute('action', btn.dataset.action);
                document.getElementById('edit_nombre').textContent = btn.dataset.nombre;
                bruto.value = btn.dataset.bruto;
                document.getElementById('edit_ssempresa').value = btn.dataset.ssempresa;
                ssTrab.value = btn.dataset.sstrab;
                irpf.value = btn.dataset.irpf;
                document.getElementById('edit_notas').value = btn.dataset.notas || '';
                calc();
                new bootstrap.Modal(document.getElementById('modalEditarNomina')).show();
            });
        });
    })();
</script>
@endpush

@push('styles')
<style>
/* Worker Profile Header */
.worker-profile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--manzer-white, #fff);
    padding: 1.5rem 2rem;
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.worker-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.75rem;
    font-weight: 700;
    color: white;
    margin-right: 1.5rem;
}

.worker-info {
    flex: 1;
}

.worker-name {
    font-size: 1.75rem;
    font-weight: 700;
    color: var(--manzer-primary, #4A7C59);
    margin: 0 0 0.5rem 0;
}

.worker-badges {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.worker-actions {
    display: flex;
    gap: 0.75rem;
}

/* Info Cards */
.info-card {
    background: var(--manzer-white, #fff);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    overflow: hidden;
    height: 100%;
}

.info-card-header {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1.25rem;
    background: linear-gradient(135deg, rgba(74, 124, 89, 0.05) 0%, rgba(74, 124, 89, 0.1) 100%);
    border-bottom: 1px solid var(--manzer-border, #e5e7eb);
}

.info-card-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 1.25rem;
}

.info-card-title {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--manzer-text-primary, #1f2937);
}

.info-card-body {
    padding: 1.25rem;
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding: 0.75rem 0;
    border-bottom: 1px solid var(--manzer-border, #e5e7eb);
}

.info-item:last-child {
    border-bottom: none;
    padding-bottom: 0;
}

.info-label {
    color: var(--manzer-text-secondary, #6b7280);
    font-size: 0.9rem;
    min-width: 100px;
}

.info-value {
    text-align: right;
    flex: 1;
    color: var(--manzer-text-primary, #1f2937);
}

/* Content Tabs */
.content-tabs-card {
    background: var(--manzer-white, #fff);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.tabs-header {
    padding: 1rem 1.5rem;
    background: linear-gradient(135deg, rgba(74, 124, 89, 0.05) 0%, rgba(74, 124, 89, 0.1) 100%);
    border-bottom: 1px solid var(--manzer-border, #e5e7eb);
}

.tabs-header .nav-pills {
    gap: 0.5rem;
}

.tabs-header .nav-link {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.75rem 1.25rem;
    border-radius: 10px;
    color: var(--manzer-text-secondary, #6b7280);
    font-weight: 500;
    transition: all 0.2s;
    background: transparent;
}

.tabs-header .nav-link:hover {
    background: rgba(74, 124, 89, 0.1);
    color: var(--manzer-primary, #4A7C59);
}

.tabs-header .nav-link.active {
    background: var(--manzer-primary, #4A7C59);
    color: white;
}

.tab-badge {
    background: rgba(0, 0, 0, 0.1);
    padding: 0.15rem 0.5rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
}

.nav-link.active .tab-badge {
    background: rgba(255, 255, 255, 0.2);
}

.tabs-content {
    padding: 1.5rem;
    min-height: 300px;
    background: #fff;
}

/* CRITICAL: Override gva-components.css line 269 that hides .tab-content */
.content-tabs-card .tab-content,
#tabsContent.tab-content {
    display: block !important;
}

/* Tab panes - hide inactive, show active */
#tabsContent .tab-pane {
    display: none !important;
}

#tabsContent .tab-pane.active {
    display: block !important;
}

.tab-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1.5rem;
    padding-bottom: 1rem;
    border-bottom: 1px solid var(--manzer-border, #e5e7eb);
}

.tab-header h5 {
    margin: 0;
    color: var(--manzer-primary, #4A7C59);
    font-weight: 600;
}

/* Empty State */
.empty-tab-state {
    text-align: center;
    padding: 3rem 2rem;
    color: var(--manzer-text-secondary, #6b7280);
}

.empty-tab-state i {
    font-size: 4rem;
    opacity: 0.3;
    margin-bottom: 1rem;
    display: block;
}

.empty-tab-state h5 {
    color: var(--manzer-text-primary, #1f2937);
    margin-bottom: 0.5rem;
}

.empty-tab-state p {
    margin-bottom: 1.5rem;
}

.empty-tab-state .btn {
    padding: 0.375rem 0.75rem !important;
    font-size: 0.875rem !important;
}

.empty-tab-state.success i {
    opacity: 0.7;
}

/* Table improvements */
.tabs-content .table {
    margin-bottom: 0;
}

.tabs-content .table thead {
    background: var(--manzer-light, #f8f9fa);
}

.tabs-content .table th {
    font-weight: 600;
    color: var(--manzer-text-primary, #1f2937);
    border-bottom: 2px solid var(--manzer-border, #e5e7eb);
    padding: 1rem;
}

.tabs-content .table td {
    padding: 1rem;
    vertical-align: middle;
}

/* Responsive */
@media (max-width: 992px) {
    .worker-profile-header {
        flex-direction: column;
        gap: 1.5rem;
        text-align: center;
    }

    .worker-profile-header > .d-flex {
        flex-direction: column;
    }

    .worker-avatar {
        margin: 0 auto 1rem;
    }

    .worker-badges {
        justify-content: center;
    }

    .worker-actions {
        width: 100%;
        justify-content: center;
    }

    .tabs-header .nav-pills {
        flex-wrap: wrap;
    }

    .tabs-header .nav-link span:not(.tab-badge) {
        display: none;
    }
}
</style>
@endpush

@push('scripts')
<script>
// Inicializar tabs manualmente si Bootstrap no lo hace
document.addEventListener('DOMContentLoaded', function() {
    const tabButtons = document.querySelectorAll('.tabs-header .nav-link');
    const tabPanes = document.querySelectorAll('.tab-pane');

    tabButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            e.preventDefault();

            // Remover active de todos los botones
            tabButtons.forEach(btn => btn.classList.remove('active'));
            // Agregar active al botón clickeado
            this.classList.add('active');

            // Obtener el target
            const targetId = this.getAttribute('data-bs-target');
            const targetPane = document.querySelector(targetId);

            // Ocultar todos los panes
            tabPanes.forEach(pane => {
                pane.classList.remove('show', 'active');
                pane.style.display = 'none';
            });

            // Mostrar el pane objetivo
            if (targetPane) {
                targetPane.classList.add('show', 'active');
                targetPane.style.display = 'block';
            }
        });
    });
});

function darBaja() {
    new bootstrap.Modal(document.getElementById('bajaModal')).show();
}

function deleteDocumento(documentoId, nombre) {
    Swal.fire({
        title: '¿Eliminar documento?',
        text: `¿Estás seguro de eliminar "${nombre}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('deleteDocumentoForm');
            form.action = `{{ url('trabajadores/' . $trabajador->id . '/documentos') }}/${documentoId}`;
            form.submit();
        }
    });
}

function deleteFormacion(formacionId, nombre) {
    Swal.fire({
        title: '¿Eliminar formación?',
        text: `¿Estás seguro de eliminar "${nombre}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('deleteFormacionForm');
            form.action = `{{ url('trabajadores/' . $trabajador->id . '/formaciones') }}/${formacionId}`;
            form.submit();
        }
    });
}

function editTrabajador(trabajadorId) {
    window.location.href = `{{ url('trabajadores') }}/${trabajadorId}/edit`;
}

// ==========================================
// EPIs - Funciones
// ==========================================
let signaturePadEntrega = null;

function verFirmaEpi(url) {
    document.getElementById('firmaEpiImage').src = url;
    new bootstrap.Modal(document.getElementById('firmaEpiModal')).show();
}

function abrirModalDevolver(epiInventarioId) {
    const form = document.getElementById('devolverEpiForm');
    form.action = `{{ url('epi-inventario') }}/${epiInventarioId}/devolver`;
    new bootstrap.Modal(document.getElementById('devolverEpiModal')).show();
}

function limpiarFirmaEntrega() {
    if (signaturePadEntrega) {
        signaturePadEntrega.clear();
    }
}

// Inicializar signature pad cuando se abre el modal
document.addEventListener('DOMContentLoaded', function() {
    const entregarEpiModal = document.getElementById('entregarEpiModal');
    if (entregarEpiModal) {
        entregarEpiModal.addEventListener('shown.bs.modal', function() {
            // Cargar EPIs disponibles
            cargarEpisDisponibles();

            // Inicializar signature pad
            const canvas = document.getElementById('signatureCanvasEntrega');
            if (canvas && typeof SignaturePad !== 'undefined') {
                // Ajustar tamaño del canvas
                const ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = canvas.offsetWidth * ratio;
                canvas.height = canvas.offsetHeight * ratio;
                canvas.getContext('2d').scale(ratio, ratio);

                signaturePadEntrega = new SignaturePad(canvas, {
                    backgroundColor: 'rgb(255, 255, 255)',
                    penColor: 'rgb(0, 0, 0)'
                });
            }
        });

        // Limpiar al cerrar
        entregarEpiModal.addEventListener('hidden.bs.modal', function() {
            if (signaturePadEntrega) {
                signaturePadEntrega.clear();
            }
        });
    }

    // Manejar submit del formulario de entrega
    const entregarEpiForm = document.getElementById('entregarEpiForm');
    if (entregarEpiForm) {
        entregarEpiForm.addEventListener('submit', function(e) {
            e.preventDefault();

            // Guardar firma en el input hidden
            if (signaturePadEntrega && !signaturePadEntrega.isEmpty()) {
                document.getElementById('firmaInputEntrega').value = signaturePadEntrega.toDataURL('image/png');
            }

            // Obtener el EPI seleccionado
            const epiId = document.getElementById('selectEpiInventario').value;
            if (!epiId) {
                Swal.fire('Error', 'Debes seleccionar un EPI', 'error');
                return;
            }

            // Actualizar action del form y enviar
            this.action = `{{ url('epi-inventario') }}/${epiId}/entregar`;
            this.submit();
        });
    }
});

function cargarEpisDisponibles() {
    const select = document.getElementById('selectEpiInventario');
    if (!select) return;

    select.innerHTML = '<option value="">Cargando...</option>';

    // Hacer peticion AJAX para obtener EPIs disponibles
    fetch('{{ route("epi-inventario.index") }}?estado=disponible&format=json')
        .then(response => response.json())
        .then(data => {
            select.innerHTML = '<option value="">Seleccionar EPI...</option>';

            if (data.inventario && data.inventario.length > 0) {
                // Agrupar por categoria
                const grupos = {};
                data.inventario.forEach(epi => {
                    const cat = epi.catalogo?.categoria || 'Sin categoria';
                    if (!grupos[cat]) grupos[cat] = [];
                    grupos[cat].push(epi);
                });

                // Crear optgroups
                Object.keys(grupos).sort().forEach(cat => {
                    const optgroup = document.createElement('optgroup');
                    optgroup.label = cat;

                    grupos[cat].forEach(epi => {
                        const option = document.createElement('option');
                        option.value = epi.id;
                        let texto = epi.catalogo?.nombre || 'EPI #' + epi.id;
                        if (epi.numero_serie) texto += ` (S/N: ${epi.numero_serie})`;
                        if (epi.fecha_caducidad) texto += ` - Cad: ${epi.fecha_caducidad}`;
                        option.textContent = texto;
                        optgroup.appendChild(option);
                    });

                    select.appendChild(optgroup);
                });
            } else {
                select.innerHTML = '<option value="">No hay EPIs disponibles</option>';
            }
        })
        .catch(error => {
            console.error('Error cargando EPIs:', error);
            select.innerHTML = '<option value="">Error al cargar EPIs</option>';
        });
}
</script>

{{-- Signature Pad Library --}}
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.2.0/dist/signature_pad.umd.min.js"></script>
@endpush
@endsection
