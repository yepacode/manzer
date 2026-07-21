@extends('layouts.app')

@section('title', 'Nóminas ' . (\App\Models\Nomina::MESES[$mes] ?? $mes) . ' ' . $anio)

@section('content')
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Nóminas de {{ \App\Models\Nomina::MESES[$mes] ?? $mes }} {{ $anio }}</h1>
            <p class="text-muted mb-0">{{ $nominas->count() }} nómina(s). Puedes editar los importes de cada una.</p>
        </div>
        <div class="d-flex gap-2">
            @if($nominas->whereNull('enviado_at')->count() > 0)
            <form method="POST" action="{{ route('nominas.mes.enviar', [$anio, $mes]) }}" onsubmit="return confirm('¿Enviar por correo los recibos pendientes de este mes?');">
                @csrf
                <button type="submit" class="btn btn-success"><i class="bi bi-envelope-paper me-2"></i>Enviar recibos pendientes ({{ $nominas->whereNull('enviado_at')->count() }})</button>
            </form>
            @endif
            <a href="{{ route('nominas.mes.exportar', [$anio, $mes]) }}" class="btn btn-outline-success">
                <i class="bi bi-file-earmark-excel me-2"></i>Exportar Excel
            </a>
            <a href="{{ route('nominas.resumen', ['anio' => $anio]) }}" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-2"></i>Volver al resumen
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible"><i class="bi bi-check-circle me-2"></i>{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible"><i class="bi bi-exclamation-triangle me-2"></i>{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Trabajador</th>
                        <th class="text-end">Bruto</th>
                        <th class="text-end">SS empresa</th>
                        <th class="text-end">SS trabajador</th>
                        <th class="text-end">IRPF</th>
                        <th class="text-end">Líquido</th>
                        <th class="text-center">Origen</th>
                        <th class="text-center">Envío</th>
                        <th class="text-center">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($nominas as $nom)
                    <tr>
                        <td class="fw-medium">
                            {{ $nom->trabajador?->apellidos }}, {{ $nom->trabajador?->nombre }}
                            <div class="small text-muted">{{ $nom->trabajador?->dni }}@if($nom->codigo_nomina) · ref. {{ $nom->codigo_nomina }}@endif</div>
                        </td>
                        <td class="text-end">{{ number_format($nom->salario_bruto, 2, ',', '.') }} €</td>
                        <td class="text-end">{{ number_format($nom->ss_empresa, 2, ',', '.') }} €</td>
                        <td class="text-end">{{ number_format($nom->ss_trabajador, 2, ',', '.') }} €</td>
                        <td class="text-end">{{ number_format($nom->irpf, 2, ',', '.') }} €</td>
                        <td class="text-end fw-semibold">{{ number_format($nom->liquido, 2, ',', '.') }} €</td>
                        <td class="text-center">
                            @if($nom->importacion_id)
                                <span class="badge bg-info text-dark" title="Creada por carga masiva">Masiva</span>
                            @else
                                <span class="badge bg-light text-dark" title="Alta manual">Manual</span>
                            @endif
                        </td>
                        <td class="text-center">
                            @if($nom->enviado_at)
                                <span class="badge bg-success" title="Enviado el {{ $nom->enviado_at->format('d/m/Y H:i') }}"><i class="bi bi-check2 me-1"></i>Enviado</span>
                            @else
                                <span class="badge bg-secondary">Pendiente</span>
                            @endif
                        </td>
                        <td class="text-center text-nowrap">
                            <form action="{{ route('nominas.enviar', $nom) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Enviar el recibo por correo al trabajador?');">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-success" title="{{ $nom->enviado_at ? 'Reenviar recibo' : 'Enviar recibo' }}"><i class="bi bi-envelope{{ $nom->enviado_at ? '-check' : '' }}"></i></button>
                            </form>
                            <button type="button" class="btn btn-sm btn-outline-primary btn-editar-nomina"
                                title="Editar"
                                data-action="{{ route('nominas.update', $nom) }}"
                                data-nombre="{{ $nom->trabajador?->apellidos }}, {{ $nom->trabajador?->nombre }}"
                                data-bruto="{{ $nom->salario_bruto }}"
                                data-ssempresa="{{ $nom->ss_empresa }}"
                                data-sstrab="{{ $nom->ss_trabajador }}"
                                data-irpf="{{ $nom->irpf }}"
                                data-notas="{{ $nom->notas }}">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <a href="{{ route('nominas.recibo', $nom) }}" target="_blank" class="btn btn-sm btn-outline-success" title="Ver recibo PDF"><i class="bi bi-file-earmark-pdf"></i></a>
                            @if($nom->documento_path)
                                <a href="{{ route('nominas.download', $nom) }}" class="btn btn-sm btn-outline-secondary" title="Descargar PDF adjunto"><i class="bi bi-download"></i></a>
                            @endif
                            <form action="{{ route('nominas.destroy', $nom) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Eliminar esta nómina?');">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar"><i class="bi bi-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" class="text-center text-muted py-4">No hay nóminas en este mes.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Modal: editar nómina --}}
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
@endsection

@push('scripts')
<script>
    (function () {
        const form = document.getElementById('formEditarNomina');
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
