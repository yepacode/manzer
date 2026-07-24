<?php

namespace App\Http\Controllers;

use App\Models\Fichaje;
use App\Models\FichajeConfiguracion;
use App\Models\Trabajador;
use App\Models\Obra;
use App\Models\EmailLog;
use App\Exports\FichajesExport;
use App\Notifications\FichajeCorregidoNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class FichajeController extends Controller
{
    /**
     * Mostrar configuración de recordatorios de fichaje.
     */
    public function configuracion()
    {
        $config = FichajeConfiguracion::obtener();
        return view('fichajes.configuracion', compact('config'));
    }

    /**
     * Guardar configuración de recordatorios de fichaje.
     */
    public function guardarConfiguracion(Request $request)
    {
        $validated = $request->validate([
            'hora_entrada' => 'required|date_format:H:i',
            'hora_salida' => 'required|date_format:H:i',
        ], [
            'hora_entrada.date_format' => 'La hora de entrada debe tener formato HH:MM.',
            'hora_salida.date_format' => 'La hora de salida debe tener formato HH:MM.',
        ]);

        $config = FichajeConfiguracion::obtener();
        $config->update([
            'activo' => $request->boolean('activo'),
            'hora_entrada' => $validated['hora_entrada'],
            'hora_salida' => $validated['hora_salida'],
        ]);

        return back()->with('success', 'Configuración de recordatorios guardada correctamente.');
    }

    /**
     * Display a listing of fichajes.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $esTrabajador = $user->hasRole('Trabajador');
        $trabajadorActual = null;
        $fichajeHoy = null;

        $query = Fichaje::with(['trabajador', 'obra', 'validadoPor']);

        // Si es trabajador, solo ver sus propios fichajes
        if ($esTrabajador) {
            $trabajadorActual = Trabajador::where('user_id', $user->id)->first();

            if (!$trabajadorActual) {
                return redirect()->route('dashboard')
                    ->with('error', 'Tu cuenta no está vinculada a un trabajador. Contacta con administración.');
            }

            $query->where('trabajador_id', $trabajadorActual->id);

            // Verificar si tiene fichaje ABIERTO (sin hora de salida)
            // Busca hoy y ayer para soportar turnos que cruzan medianoche
            $fichajeHoy = Fichaje::where('trabajador_id', $trabajadorActual->id)
                                 ->where('fecha', '>=', now()->subDay()->toDateString())
                                 ->whereNull('hora_salida')
                                 ->orderBy('id', 'desc')
                                 ->first();
        }

        // Filtros
        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        }

        // Solo admin/encargados pueden filtrar por trabajador
        if (!$esTrabajador && $request->filled('trabajador_id')) {
            $query->where('trabajador_id', $request->trabajador_id);
        }

        // Filtro por obra (disponible para todos)
        if ($request->filled('obra_id')) {
            $query->where('obra_id', $request->obra_id);
        }

        // Filtro por cuadrilla (solo admin/encargados)
        if (!$esTrabajador && $request->filled('cuadrilla_id')) {
            $trabajadorIdsCuadrilla = \DB::table('cuadrilla_trabajadores')
                ->where('cuadrilla_id', $request->cuadrilla_id)
                ->where('activo', true)
                ->pluck('trabajador_id')
                ->toArray();

            // Incluir también al capataz de la cuadrilla
            $capatazId = \App\Models\Cuadrilla::where('id', $request->cuadrilla_id)->value('capataz_id');
            if ($capatazId) {
                $trabajadorIdsCuadrilla[] = $capatazId;
            }

            $query->whereIn('trabajador_id', array_unique($trabajadorIdsCuadrilla));
        }

        if ($request->filled('validado')) {
            $query->where('validado', $request->validado === '1');
        }

        // Por defecto mostrar el mes actual
        if (!$request->filled('fecha_desde') && !$request->filled('fecha_hasta')) {
            $query->where('fecha', '>=', now()->startOfMonth())
                  ->where('fecha', '<=', now()->endOfMonth());
        }

        $fichajes = $query->orderBy('fecha', 'desc')
                          ->orderBy('hora_entrada', 'desc')
                          ->paginate(50);

        // Estadísticas del período - OPTIMIZADAS con una sola consulta
        $statsBaseQuery = $esTrabajador && $trabajadorActual
            ? Fichaje::where('trabajador_id', $trabajadorActual->id)
            : Fichaje::query();

        $statsBaseQuery->where('fecha', '>=', now()->startOfMonth());

        $statsData = (clone $statsBaseQuery)
            ->selectRaw('
                COUNT(*) as total_fichajes,
                SUM(CASE WHEN validado = 0 THEN 1 ELSE 0 END) as pendientes_validar,
                COALESCE(SUM(horas_trabajadas), 0) as horas_totales,
                COALESCE(SUM(horas_extra), 0) as horas_extra
            ')
            ->first();

        $stats = [
            'total_fichajes' => $statsData->total_fichajes ?? 0,
            'pendientes_validar' => $statsData->pendientes_validar ?? 0,
            'horas_totales' => $statsData->horas_totales ?? 0,
            'horas_extra' => $statsData->horas_extra ?? 0,
        ];

        $trabajadores = Trabajador::where('activo', true)
                                   ->orderBy('nombre')
                                   ->get();

        $cuadrillas = \App\Models\Cuadrilla::where('activa', true)->orderBy('nombre')->get();

        // Filtrar obras según rol
        if ($esTrabajador && $trabajadorActual) {
            $obras = $trabajadorActual->obrasAsignadas();
        } else {
            $obras = Obra::whereIn('estado', ['en_curso', 'aprobada'])
                         ->orderBy('nombre')
                         ->get();
        }

        return view('fichajes.index', compact(
            'fichajes', 'trabajadores', 'obras', 'cuadrillas', 'stats',
            'esTrabajador', 'trabajadorActual', 'fichajeHoy'
        ));
    }

    /**
     * Exportar fichajes a Excel.
     */
    public function exportExcel(Request $request)
    {
        $fichajes = $this->getFilteredFichajes($request);

        $filename = 'fichajes_' . now()->format('Y-m-d_H-i') . '.xlsx';

        return Excel::download(new FichajesExport($fichajes), $filename);
    }

    /**
     * Exportar fichajes a PDF.
     */
    public function exportPdf(Request $request)
    {
        $fichajes = $this->getFilteredFichajes($request);

        $pdf = Pdf::loadView('fichajes.pdf', [
            'fichajes' => $fichajes,
            'fechaDesde' => $request->input('fecha_desde', now()->startOfMonth()->format('d/m/Y')),
            'fechaHasta' => $request->input('fecha_hasta', now()->endOfMonth()->format('d/m/Y')),
        ]);

        $pdf->setPaper('a4', 'landscape');

        return $pdf->download('fichajes_' . now()->format('Y-m-d_H-i') . '.pdf');
    }

    /**
     * Obtener fichajes filtrados para exportación.
     */
    private function getFilteredFichajes(Request $request)
    {
        $user = Auth::user();
        $esTrabajador = $user->hasRole('Trabajador');

        $query = Fichaje::with(['trabajador', 'obra', 'validadoPor']);

        if ($esTrabajador) {
            $trabajadorActual = Trabajador::where('user_id', $user->id)->first();
            if ($trabajadorActual) {
                $query->where('trabajador_id', $trabajadorActual->id);
            }
        }

        if ($request->filled('fecha_desde')) {
            $query->where('fecha', '>=', $request->fecha_desde);
        } else {
            $query->where('fecha', '>=', now()->startOfMonth());
        }

        if ($request->filled('fecha_hasta')) {
            $query->where('fecha', '<=', $request->fecha_hasta);
        } else {
            $query->where('fecha', '<=', now()->endOfMonth());
        }

        if (!$esTrabajador && $request->filled('trabajador_id')) {
            $query->where('trabajador_id', $request->trabajador_id);
        }

        if ($request->filled('obra_id')) {
            $query->where('obra_id', $request->obra_id);
        }

        // Filtro por cuadrilla
        if (!$esTrabajador && $request->filled('cuadrilla_id')) {
            $trabajadorIdsCuadrilla = \DB::table('cuadrilla_trabajadores')
                ->where('cuadrilla_id', $request->cuadrilla_id)
                ->where('activo', true)
                ->pluck('trabajador_id')
                ->toArray();

            $capatazId = \App\Models\Cuadrilla::where('id', $request->cuadrilla_id)->value('capataz_id');
            if ($capatazId) {
                $trabajadorIdsCuadrilla[] = $capatazId;
            }

            $query->whereIn('trabajador_id', array_unique($trabajadorIdsCuadrilla));
        }

        if ($request->filled('validado')) {
            $query->where('validado', $request->validado === '1');
        }

        return $query->orderBy('fecha', 'desc')
                     ->orderBy('hora_entrada', 'desc')
                     ->get();
    }

    /**
     * Show the form for creating a new fichaje.
     */
    public function create()
    {
        $user = Auth::user();
        $esTrabajador = $user->hasRole('Trabajador');
        $trabajadorActual = null;

        // Si es trabajador, solo puede fichar para sí mismo
        if ($esTrabajador) {
            $trabajadorActual = Trabajador::where('user_id', $user->id)->first();

            if (!$trabajadorActual) {
                return redirect()->route('fichajes.index')
                    ->with('error', 'Tu cuenta de usuario no está vinculada a un trabajador. Contacta con administración.');
            }

            $trabajadores = collect([$trabajadorActual]);

            // Solo obras asignadas al trabajador (directas + via cuadrilla)
            $obras = $trabajadorActual->obrasAsignadas();
        } else {
            $trabajadores = Trabajador::where('activo', true)
                                       ->orderBy('nombre')
                                       ->get();

            // Admin ve todas las obras activas inicialmente
            $obras = Obra::whereIn('estado', ['en_curso', 'aprobada'])
                         ->orderBy('nombre')
                         ->get();
        }

        return view('fichajes.create', compact('trabajadores', 'obras', 'esTrabajador', 'trabajadorActual'));
    }

    /**
     * Store a newly created fichaje.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'trabajador_id' => 'required|exists:trabajadores,id',
            'obra_id' => 'nullable|exists:obras,id',
            'fecha' => 'required|date',
            'hora_entrada' => 'nullable|date_format:H:i',
            'hora_salida' => 'nullable|date_format:H:i|after:hora_entrada',
            'latitud_entrada' => 'nullable|numeric',
            'longitud_entrada' => 'nullable|numeric',
            'notas' => 'nullable|string|max:1000',
        ]);

        // Teléfono del trabajador (se asocia al fichaje).
        $trabajador = Trabajador::find($validated['trabajador_id']);
        $telefono = trim((string) ($trabajador->telefono ?? '')) ?: null;

        // Si es el propio trabajador fichando: ubicación y teléfono OBLIGATORIOS.
        $user = Auth::user();
        if ($user && $user->hasRole('Trabajador')) {
            if (empty($validated['latitud_entrada']) || empty($validated['longitud_entrada'])) {
                return back()->withErrors(['latitud_entrada' => 'Debes permitir la ubicación (GPS) para poder fichar.'])->withInput();
            }
            if (!$telefono) {
                return back()->withErrors(['telefono' => 'No tienes un teléfono registrado en tu ficha. Contacta con RRHH para poder fichar.'])->withInput();
            }
        }

        // Calcular horas trabajadas con configuración
        [$horasTrabajadas, $horasExtra] = $this->calcularHoras(
            $validated['hora_entrada'] ?? null,
            $validated['hora_salida'] ?? null
        );

        $fichaje = Fichaje::create([
            'trabajador_id' => $validated['trabajador_id'],
            'obra_id' => $validated['obra_id'] ?? null,
            'fecha' => $validated['fecha'],
            'hora_entrada' => $validated['hora_entrada'] ?? null,
            'hora_salida' => $validated['hora_salida'] ?? null,
            'latitud_entrada' => $validated['latitud_entrada'] ?? null,
            'longitud_entrada' => $validated['longitud_entrada'] ?? null,
            'telefono_entrada' => $telefono,
            'horas_trabajadas' => $horasTrabajadas,
            'horas_extra' => $horasExtra,
            'notas' => $validated['notas'] ?? null,
        ]);

        return redirect()->route('fichajes.index')
                         ->with('success', 'Fichaje registrado correctamente.');
    }

    /**
     * Calcular horas trabajadas y extras con configuración.
     */
    private function calcularHoras(?string $horaEntrada, ?string $horaSalida): array
    {
        if (empty($horaEntrada) || empty($horaSalida)) {
            return [null, 0];
        }

        $entrada = Carbon::createFromFormat('H:i', $horaEntrada);
        $salida = Carbon::createFromFormat('H:i', $horaSalida);
        $minutosTrabajados = $salida->diffInMinutes($entrada);
        $horasTotales = $minutosTrabajados / 60;

        // Obtener configuración
        $horasJornada = config('fichajes.horas_jornada_normal', 8);
        $pausaObligatoria = config('fichajes.pausa_obligatoria', 0.5);
        $horasMinimasPausa = config('fichajes.horas_minimas_pausa', 6);

        // Aplicar pausa obligatoria si corresponde
        if ($horasTotales >= $horasMinimasPausa && $pausaObligatoria > 0) {
            $horasTotales -= $pausaObligatoria;
        }

        // Calcular horas normales y extra
        $horasTrabajadas = min($horasTotales, $horasJornada);
        $horasExtra = max(0, $horasTotales - $horasJornada);

        return [round($horasTrabajadas, 2), round($horasExtra, 2)];
    }

    /**
     * Display the specified fichaje.
     */
    public function show(Fichaje $fichaje)
    {
        $fichaje->load(['trabajador', 'obra', 'validadoPor', 'corregidoPor']);

        return view('fichajes.show', compact('fichaje'));
    }

    /**
     * Obtener detalles de un fichaje para modal (AJAX).
     */
    public function getDetails(Fichaje $fichaje)
    {
        $fichaje->load(['trabajador', 'obra', 'validadoPor', 'corregidoPor']);

        return response()->json([
            'id' => $fichaje->id,
            'fecha' => $fichaje->fecha->format('d/m/Y'),
            'dia' => $fichaje->fecha->translatedFormat('l'),
            'trabajador' => $fichaje->trabajador ? $fichaje->trabajador->nombre . ' ' . $fichaje->trabajador->apellidos : '-',
            'obra' => $fichaje->obra ? $fichaje->obra->nombre : '-',
            'hora_entrada' => $fichaje->hora_entrada ? Carbon::parse($fichaje->hora_entrada)->format('H:i') : '-',
            'hora_salida' => $fichaje->hora_salida ? Carbon::parse($fichaje->hora_salida)->format('H:i') : '-',
            'horas_trabajadas' => $fichaje->horas_trabajadas ? number_format($fichaje->horas_trabajadas, 2) : '-',
            'horas_extra' => $fichaje->horas_extra ? number_format($fichaje->horas_extra, 2) : '0',
            'validado' => $fichaje->validado,
            'validado_por' => $fichaje->validadoPor ? $fichaje->validadoPor->name : null,
            'fecha_validacion' => $fichaje->fecha_validacion ? $fichaje->fecha_validacion->format('d/m/Y H:i') : null,
            'corregido' => $fichaje->corregido,
            'corregido_por' => $fichaje->corregidoPor ? $fichaje->corregidoPor->name : null,
            'motivo_correccion' => $fichaje->motivo_correccion,
            'notas' => $fichaje->notas,
            'ubicacion_entrada' => $fichaje->latitud_entrada && $fichaje->longitud_entrada
                ? ['lat' => $fichaje->latitud_entrada, 'lng' => $fichaje->longitud_entrada]
                : null,
            'ubicacion_salida' => $fichaje->latitud_salida && $fichaje->longitud_salida
                ? ['lat' => $fichaje->latitud_salida, 'lng' => $fichaje->longitud_salida]
                : null,
            'telefono_entrada' => $fichaje->telefono_entrada,
            'telefono_salida' => $fichaje->telefono_salida,
        ]);
    }

    /**
     * Show the form for editing the specified fichaje.
     */
    public function edit(Fichaje $fichaje)
    {
        $trabajadores = Trabajador::where('activo', true)
                                   ->orderBy('nombre')
                                   ->get();

        // Filtrar obras según el trabajador del fichaje
        $trabajadorFichaje = $fichaje->trabajador;
        if ($trabajadorFichaje) {
            $obras = $trabajadorFichaje->obrasAsignadas();
            // Asegurar que la obra actual del fichaje esté incluida (aunque ya no esté asignada)
            if ($fichaje->obra_id && !$obras->contains('id', $fichaje->obra_id)) {
                $obraActual = Obra::find($fichaje->obra_id);
                if ($obraActual) {
                    $obras->push($obraActual);
                }
            }
        } else {
            $obras = Obra::whereIn('estado', ['en_curso', 'aprobada'])
                         ->orderBy('nombre')
                         ->get();
        }

        return view('fichajes.edit', compact('fichaje', 'trabajadores', 'obras'));
    }

    /**
     * Update the specified fichaje.
     */
    public function update(Request $request, Fichaje $fichaje)
    {
        $validated = $request->validate([
            'obra_id' => 'nullable|exists:obras,id',
            'hora_entrada' => 'nullable|date_format:H:i',
            'hora_salida' => 'nullable|date_format:H:i|after:hora_entrada',
            'motivo_correccion' => 'required_if:corregido,true|nullable|string|max:500',
            'notas' => 'nullable|string|max:1000',
        ]);

        // Calcular horas trabajadas con configuración
        [$horasTrabajadas, $horasExtra] = $this->calcularHoras(
            $validated['hora_entrada'] ?? null,
            $validated['hora_salida'] ?? null
        );

        // Detectar si hay corrección
        $esCorreccion = $fichaje->hora_entrada != $validated['hora_entrada'] ||
                        $fichaje->hora_salida != $validated['hora_salida'];

        $fichaje->update([
            'obra_id' => $validated['obra_id'] ?? null,
            'hora_entrada' => $validated['hora_entrada'] ?? null,
            'hora_salida' => $validated['hora_salida'] ?? null,
            'horas_trabajadas' => $horasTrabajadas,
            'horas_extra' => $horasExtra,
            'notas' => $validated['notas'] ?? null,
            'corregido' => $esCorreccion ? true : $fichaje->corregido,
            'corregido_por' => $esCorreccion ? Auth::id() : $fichaje->corregido_por,
            'motivo_correccion' => $esCorreccion ? $validated['motivo_correccion'] : $fichaje->motivo_correccion,
        ]);

        // Enviar notificación al trabajador si hubo corrección
        if ($esCorreccion && $fichaje->trabajador->user) {
            try {
                $fichaje->load(['trabajador', 'obra', 'corregidoPor']);
                $fichaje->trabajador->user->notify(new FichajeCorregidoNotification($fichaje));

                EmailLog::logEnviado(
                    EmailLog::TIPO_FICHAJE,
                    $fichaje->trabajador->user->email,
                    "Fichaje corregido - {$fichaje->fecha->format('d/m/Y')}",
                    $fichaje,
                    $fichaje->trabajador->user->id
                );
            } catch (\Exception $e) {
                Log::error("Error enviando notificación de fichaje corregido", [
                    'fichaje_id' => $fichaje->id,
                    'trabajador_id' => $fichaje->trabajador_id,
                    'error' => $e->getMessage()
                ]);

                EmailLog::logFallido(
                    EmailLog::TIPO_FICHAJE,
                    $fichaje->trabajador->user->email,
                    "Fichaje corregido - {$fichaje->fecha->format('d/m/Y')}",
                    $e->getMessage(),
                    $fichaje,
                    $fichaje->trabajador->user->id
                );
            }
        }

        return redirect()->route('fichajes.index')
                         ->with('success', 'Fichaje actualizado correctamente.');
    }

    /**
     * Remove the specified fichaje.
     */
    public function destroy(Fichaje $fichaje)
    {
        $fichaje->delete();

        return redirect()->route('fichajes.index')
                         ->with('success', 'Fichaje eliminado correctamente.');
    }

    /**
     * Validar un fichaje.
     */
    public function validar(Fichaje $fichaje)
    {
        if ($fichaje->validado) {
            return back()->with('error', 'Este fichaje ya está validado.');
        }

        $fichaje->update([
            'validado' => true,
            'validado_por' => Auth::id(),
            'fecha_validacion' => now(),
        ]);

        return back()->with('success', 'Fichaje validado correctamente.');
    }

    /**
     * Validar múltiples fichajes.
     */
    public function validarMultiple(Request $request)
    {
        $validated = $request->validate([
            'fichajes' => 'required|array',
            'fichajes.*' => 'exists:fichajes,id',
        ], [
            'fichajes.required' => 'Debe seleccionar al menos un fichaje para validar.',
            'fichajes.array' => 'Selección de fichajes inválida.',
        ]);

        $count = Fichaje::whereIn('id', $validated['fichajes'])
               ->where('validado', false)
               ->update([
                   'validado' => true,
                   'validado_por' => Auth::id(),
                   'fecha_validacion' => now(),
               ]);

        return back()->with('success', $count . ' fichaje(s) validado(s) correctamente.');
    }

    /**
     * Registrar entrada (check-in).
     */
    public function checkIn(Request $request)
    {
        $validated = $request->validate([
            'trabajador_id' => 'required|exists:trabajadores,id',
            'obra_id' => 'nullable|exists:obras,id',
            'latitud' => 'required|numeric',
            'longitud' => 'required|numeric',
        ], [
            'latitud.required' => 'Debes permitir la ubicación (GPS) para poder fichar.',
            'longitud.required' => 'Debes permitir la ubicación (GPS) para poder fichar.',
        ]);

        // Validar que el usuario tenga permiso para fichar por este trabajador
        $user = Auth::user();
        if ($user->hasRole('Trabajador')) {
            $trabajadorUsuario = Trabajador::where('user_id', $user->id)->first();
            if (!$trabajadorUsuario || $trabajadorUsuario->id != $validated['trabajador_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para fichar por este trabajador.',
                ], 403);
            }
        }

        // Teléfono OBLIGATORIO: se toma de la ficha del trabajador.
        $trabajador = Trabajador::find($validated['trabajador_id']);
        $telefono = trim((string) ($trabajador->telefono ?? ''));
        if ($telefono === '') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un teléfono registrado en tu ficha. Contacta con RRHH para poder fichar.',
            ], 422);
        }

        $hoy = now()->toDateString();

        // Verificar si hay un fichaje abierto (hoy o ayer para turnos nocturnos)
        $fichajeAbierto = Fichaje::where('trabajador_id', $validated['trabajador_id'])
                          ->where('fecha', '>=', now()->subDay()->toDateString())
                          ->whereNull('hora_salida')
                          ->first();

        if ($fichajeAbierto && $fichajeAbierto->hora_entrada) {
            return response()->json([
                'success' => false,
                'message' => 'Ya tienes un fichaje abierto. Debes fichar salida antes de fichar una nueva entrada.',
            ], 400);
        }

        // Crear nuevo fichaje (permite múltiples por día)
        $fichaje = Fichaje::create([
            'trabajador_id' => $validated['trabajador_id'],
            'obra_id' => $validated['obra_id'] ?? null,
            'fecha' => $hoy,
            'hora_entrada' => now()->format('H:i'),
            'latitud_entrada' => $validated['latitud'],
            'longitud_entrada' => $validated['longitud'],
            'telefono_entrada' => $telefono,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Entrada registrada correctamente.',
            'fichaje' => $fichaje,
        ]);
    }

    /**
     * Registrar salida (check-out).
     */
    public function checkOut(Request $request)
    {
        $validated = $request->validate([
            'trabajador_id' => 'required|exists:trabajadores,id',
            'latitud' => 'required|numeric',
            'longitud' => 'required|numeric',
        ], [
            'latitud.required' => 'Debes permitir la ubicación (GPS) para poder fichar.',
            'longitud.required' => 'Debes permitir la ubicación (GPS) para poder fichar.',
        ]);

        // Validar que el usuario tenga permiso para fichar por este trabajador
        $user = Auth::user();
        if ($user->hasRole('Trabajador')) {
            $trabajadorUsuario = Trabajador::where('user_id', $user->id)->first();
            if (!$trabajadorUsuario || $trabajadorUsuario->id != $validated['trabajador_id']) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permiso para fichar por este trabajador.',
                ], 403);
            }
        }

        // Teléfono OBLIGATORIO: se toma de la ficha del trabajador.
        $trabajador = Trabajador::find($validated['trabajador_id']);
        $telefono = trim((string) ($trabajador->telefono ?? ''));
        if ($telefono === '') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes un teléfono registrado en tu ficha. Contacta con RRHH para poder fichar.',
            ], 422);
        }

        $hoy = now()->toDateString();

        // Buscar fichaje abierto (hoy o ayer para turnos nocturnos)
        $fichaje = Fichaje::where('trabajador_id', $validated['trabajador_id'])
                          ->where('fecha', '>=', now()->subDay()->toDateString())
                          ->whereNotNull('hora_entrada')
                          ->whereNull('hora_salida')
                          ->orderBy('id', 'desc')
                          ->first();

        if (!$fichaje) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes ningún fichaje abierto. Debes fichar entrada primero.',
            ], 400);
        }

        // Calcular horas con configuración
        $entrada = Carbon::parse($fichaje->hora_entrada);
        $salida = now();
        [$horasTrabajadas, $horasExtra] = $this->calcularHoras(
            $entrada->format('H:i'),
            $salida->format('H:i')
        );

        $fichaje->update([
            'hora_salida' => $salida->format('H:i'),
            'latitud_salida' => $validated['latitud'],
            'longitud_salida' => $validated['longitud'],
            'telefono_salida' => $telefono,
            'horas_trabajadas' => $horasTrabajadas,
            'horas_extra' => $horasExtra,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Salida registrada correctamente.',
            'fichaje' => $fichaje,
        ]);
    }

    /**
     * Obtener obras asignadas a un trabajador (AJAX).
     */
    public function getObrasTrabajador(Trabajador $trabajador)
    {
        $obras = $trabajador->obrasAsignadas();

        return response()->json([
            'success' => true,
            'obras' => $obras->map(fn($obra) => [
                'id' => $obra->id,
                'nombre' => $obra->nombre,
            ]),
        ]);
    }

    /**
     * Resumen de fichajes por trabajador.
     */
    public function resumen(Request $request)
    {
        $mes = $request->input('mes', now()->month);
        $anio = $request->input('anio', now()->year);

        $trabajadores = Trabajador::where('activo', true)
            ->with(['fichajes' => function($query) use ($mes, $anio) {
                $query->whereMonth('fecha', $mes)
                      ->whereYear('fecha', $anio);
            }])
            ->get()
            ->map(function($trabajador) {
                return [
                    'id' => $trabajador->id,
                    'nombre' => $trabajador->nombre_completo,
                    'dias_trabajados' => $trabajador->fichajes->count(),
                    'horas_trabajadas' => $trabajador->fichajes->sum('horas_trabajadas'),
                    'horas_extra' => $trabajador->fichajes->sum('horas_extra'),
                    'fichajes_pendientes' => $trabajador->fichajes->where('validado', false)->count(),
                ];
            });

        return view('fichajes.resumen', compact('trabajadores', 'mes', 'anio'));
    }
}
