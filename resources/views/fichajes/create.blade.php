@extends('layouts.app')

@section('title', 'Nuevo Fichaje')

@section('content')
<div class="container-fluid py-4">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-1">Nuevo Fichaje</h1>
            <p class="text-muted mb-0">
                @if($esTrabajador ?? false)
                    Registrar tu entrada/salida del día
                @else
                    Registrar entrada/salida manualmente
                @endif
            </p>
        </div>
        <a href="{{ route('fichajes.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-2"></i>Volver
        </a>
    </div>

    <div class="row">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <form action="{{ route('fichajes.store') }}" method="POST" id="fichajeForm">
                        @csrf

                        {{-- Campos ocultos para GPS --}}
                        <input type="hidden" name="latitud_entrada" id="latitud_entrada">
                        <input type="hidden" name="longitud_entrada" id="longitud_entrada">
                        <input type="hidden" name="precision_entrada" id="precision_entrada">

                        <div class="row g-3">
                            <!-- Trabajador -->
                            <div class="col-md-6">
                                <label class="form-label">Trabajador <span class="text-danger">*</span></label>
                                @if($esTrabajador ?? false)
                                    {{-- Si es trabajador, mostrar solo su nombre y campo oculto --}}
                                    <input type="hidden" name="trabajador_id" value="{{ $trabajadorActual->id }}">
                                    <input type="text" class="form-control" value="{{ $trabajadorActual->nombre }} {{ $trabajadorActual->apellidos }}" readonly>
                                @else
                                    <select name="trabajador_id" class="form-select @error('trabajador_id') is-invalid @enderror" required>
                                        <option value="">Seleccionar trabajador...</option>
                                        @foreach($trabajadores as $trabajador)
                                            <option value="{{ $trabajador->id }}" {{ old('trabajador_id') == $trabajador->id ? 'selected' : '' }}>
                                                {{ $trabajador->nombre }} {{ $trabajador->apellidos }}
                                            </option>
                                        @endforeach
                                    </select>
                                @endif
                                @error('trabajador_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Obra -->
                            <div class="col-md-6">
                                <label class="form-label">Obra</label>
                                <select name="obra_id" class="form-select @error('obra_id') is-invalid @enderror">
                                    <option value="">Sin asignar</option>
                                    @foreach($obras as $obra)
                                        <option value="{{ $obra->id }}" {{ old('obra_id') == $obra->id ? 'selected' : '' }}>
                                            {{ $obra->nombre }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('obra_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Fecha -->
                            <div class="col-md-4">
                                <label class="form-label">Fecha <span class="text-danger">*</span></label>
                                @if($esTrabajador ?? false)
                                    {{-- Trabajador solo puede fichar hoy --}}
                                    <input type="date" name="fecha" class="form-control" value="{{ date('Y-m-d') }}" readonly>
                                @else
                                    <input type="date" name="fecha" class="form-control @error('fecha') is-invalid @enderror"
                                           value="{{ old('fecha', date('Y-m-d')) }}" required>
                                @endif
                                @error('fecha')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Hora Entrada -->
                            <div class="col-md-4">
                                <label class="form-label">Hora Entrada</label>
                                @if($esTrabajador ?? false)
                                    <input type="time" name="hora_entrada" id="hora_entrada" class="form-control @error('hora_entrada') is-invalid @enderror"
                                           value="{{ old('hora_entrada', date('H:i')) }}" readonly>
                                @else
                                    <input type="time" name="hora_entrada" class="form-control @error('hora_entrada') is-invalid @enderror"
                                           value="{{ old('hora_entrada') }}">
                                @endif
                                @error('hora_entrada')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <!-- Hora Salida -->
                            <div class="col-md-4">
                                <label class="form-label">Hora Salida</label>
                                @if($esTrabajador ?? false)
                                    <input type="time" name="hora_salida" class="form-control" value="" placeholder="Se registra al salir" disabled>
                                    <small class="text-muted">La salida se registra después</small>
                                @else
                                    <input type="time" name="hora_salida" class="form-control @error('hora_salida') is-invalid @enderror"
                                           value="{{ old('hora_salida') }}">
                                @endif
                                @error('hora_salida')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            @if($esTrabajador ?? false)
                            <!-- Ubicación GPS (solo para trabajadores) -->
                            <div class="col-12">
                                <div class="alert alert-info mb-0" id="gpsStatus">
                                    <i class="bi bi-geo-alt me-2"></i>
                                    <span id="gpsMessage">Obteniendo ubicación...</span>
                                </div>
                            </div>
                            @endif

                            <!-- Notas -->
                            <div class="col-12">
                                <label class="form-label">Notas</label>
                                <textarea name="notas" class="form-control" rows="3"
                                          placeholder="Observaciones o comentarios...">{{ old('notas') }}</textarea>
                            </div>

                            <div class="col-12">
                                <hr class="my-3">
                                <div class="d-flex justify-content-end gap-2">
                                    <a href="{{ route('fichajes.index') }}" class="btn btn-secondary">Cancelar</a>
                                    <button type="submit" class="btn btn-primary" id="btnSubmit">
                                        @if($esTrabajador ?? false)
                                            <i class="bi bi-box-arrow-in-right me-2"></i>Fichar Entrada
                                        @else
                                            <i class="bi bi-check-lg me-2"></i>Registrar Fichaje
                                        @endif
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm bg-light">
                <div class="card-body">
                    <h6 class="card-title">
                        <i class="bi bi-info-circle me-2"></i>Información
                    </h6>
                    @if($esTrabajador ?? false)
                        <p class="card-text small text-muted">
                            Al fichar entrada se registrará automáticamente:
                        </p>
                        <ul class="small text-muted mb-0">
                            <li>La hora actual del sistema</li>
                            <li>Tu ubicación GPS (si está disponible)</li>
                            <li>La obra seleccionada</li>
                        </ul>
                        <hr>
                        <p class="card-text small text-muted mb-0">
                            <strong>Para fichar salida:</strong> Ve al listado de fichajes y usa el botón "Fichar Salida" en tu registro de hoy.
                        </p>
                    @else
                        <p class="card-text small text-muted">
                            Este formulario es para registrar fichajes manualmente. Las horas se calculan automáticamente
                            cuando se indican tanto la hora de entrada como la de salida.
                        </p>
                        <hr>
                        <h6 class="text-muted small">Cálculo de horas:</h6>
                        <ul class="small text-muted mb-0">
                            <li>Las primeras 8 horas son horas normales</li>
                            <li>Las horas adicionales se marcan como extras</li>
                            <li>Solo puede haber un fichaje por trabajador/día</li>
                        </ul>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    @if($esTrabajador ?? false)
    const latInput = document.getElementById('latitud_entrada');
    const lngInput = document.getElementById('longitud_entrada');
    const gpsStatus = document.getElementById('gpsStatus');
    const gpsMessage = document.getElementById('gpsMessage');
    const btnSubmit = document.getElementById('btnSubmit');

    // Actualizar hora actual
    document.getElementById('hora_entrada').value = new Date().toTimeString().slice(0, 5);

    // Obtener ubicación GPS (con opción de reintentar)
    const precInput = document.getElementById('precision_entrada');

    function obtenerUbicacion() {
        if (!navigator.geolocation) {
            gpsStatus.className = 'alert alert-danger mb-0';
            gpsMessage.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> Tu navegador no soporta geolocalización. No puedes fichar sin ubicación.';
            btnSubmit.disabled = true;
            return;
        }
        btnSubmit.disabled = true;
        gpsStatus.className = 'alert alert-info mb-0';
        gpsMessage.textContent = 'Obteniendo ubicación GPS...';

        navigator.geolocation.getCurrentPosition(
            function(position) {
                latInput.value = position.coords.latitude;
                lngInput.value = position.coords.longitude;
                precInput.value = position.coords.accuracy || '';
                gpsStatus.className = 'alert alert-success mb-0';
                gpsMessage.innerHTML = '<i class="bi bi-check-circle me-1"></i> Ubicación obtenida: ' +
                    position.coords.latitude.toFixed(6) + ', ' + position.coords.longitude.toFixed(6) +
                    (position.coords.accuracy ? ' (±' + Math.round(position.coords.accuracy) + ' m)' : '');
                btnSubmit.disabled = false;
            },
            function(error) {
                let mensaje = 'No se pudo obtener la ubicación: ';
                switch(error.code) {
                    case error.PERMISSION_DENIED: mensaje += 'Permiso denegado. Activa la ubicación en tu navegador.'; break;
                    case error.POSITION_UNAVAILABLE: mensaje += 'Ubicación no disponible.'; break;
                    case error.TIMEOUT: mensaje += 'Tiempo de espera agotado.'; break;
                    default: mensaje += 'Error desconocido.';
                }
                gpsStatus.className = 'alert alert-danger mb-0';
                gpsMessage.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i> ' + mensaje + ' No puedes fichar sin ubicación.' +
                    ' <button type="button" id="btnReintentarGps" class="btn btn-sm btn-outline-danger ms-2"><i class="bi bi-arrow-clockwise me-1"></i>Reintentar</button>';
                btnSubmit.disabled = true;
                const r = document.getElementById('btnReintentarGps');
                if (r) r.addEventListener('click', obtenerUbicacion);
            },
            { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
        );
    }

    obtenerUbicacion();
    @else
    // Admin/Encargado: filtrar obras dinámicamente al seleccionar trabajador
    const selectTrabajador = document.querySelector('select[name="trabajador_id"]');
    const selectObra = document.querySelector('select[name="obra_id"]');

    if (selectTrabajador && selectObra) {
        // Guardar opciones originales
        const todasLasObras = Array.from(selectObra.options).map(opt => ({
            value: opt.value,
            text: opt.text
        }));

        selectTrabajador.addEventListener('change', function() {
            const trabajadorId = this.value;

            if (!trabajadorId) {
                // Sin trabajador seleccionado: restaurar todas las obras
                selectObra.innerHTML = '<option value="">Sin asignar</option>';
                todasLasObras.forEach(function(obra) {
                    if (obra.value) {
                        selectObra.add(new Option(obra.text, obra.value));
                    }
                });
                return;
            }

            selectObra.disabled = true;
            selectObra.innerHTML = '<option value="">Cargando obras...</option>';

            fetch('/fichajes/ajax/obras-trabajador/' + trabajadorId)
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    selectObra.innerHTML = '<option value="">Sin asignar</option>';

                    if (data.success && data.obras.length > 0) {
                        data.obras.forEach(function(obra) {
                            selectObra.add(new Option(obra.nombre, obra.id));
                        });
                    } else {
                        selectObra.innerHTML = '<option value="">-- Sin obras asignadas --</option>';
                    }

                    selectObra.disabled = false;
                })
                .catch(function() {
                    // Error: restaurar todas las obras
                    selectObra.innerHTML = '<option value="">Sin asignar</option>';
                    todasLasObras.forEach(function(obra) {
                        if (obra.value) {
                            selectObra.add(new Option(obra.text, obra.value));
                        }
                    });
                    selectObra.disabled = false;
                });
        });
    }
    @endif
});
</script>
@endpush
