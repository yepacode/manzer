<?php

namespace App\Http\Controllers;

use App\Exports\GridArrayExport;
use App\Exports\NominasPlantillaExport;
use App\Imports\NominasImport;
use App\Services\GestoriaNominaExporter;
use App\Models\Nomina;
use App\Models\NominaImportacion;
use App\Mail\ReciboNominaMail;
use App\Models\Trabajador;
use App\Services\GestoriaNominaMatcher;
use App\Services\GestoriaNominaParser;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Carga masiva de nóminas (plantilla, previsualización, confirmación y bitácora).
 * Es 100% aditivo: no toca el flujo manual existente de NominaController.
 */
class NominaImportacionController extends Controller
{
    /**
     * Descarga la plantilla de autollenado: una fila por trabajador activo
     * (o por los trabajadores seleccionados), con el año/mes elegidos.
     */
    public function plantilla(Request $request)
    {
        $validated = $request->validate([
            'anio' => 'nullable|integer|min:2000|max:2100',
            'mes' => 'nullable|integer|min:1|max:12',
            'trabajadores' => 'nullable|array',
            'trabajadores.*' => 'integer|exists:trabajadores,id',
        ]);

        $anio = (int) ($validated['anio'] ?? now()->year);
        $mes = (int) ($validated['mes'] ?? now()->month);

        $query = Trabajador::where('activo', true);

        // Selección: si se envían trabajadores, solo esos; si no, todos los activos.
        if (!empty($validated['trabajadores'])) {
            $query->whereIn('id', $validated['trabajadores']);
        }

        $trabajadores = $query->orderBy('apellidos')->orderBy('nombre')->get();

        $nombreArchivo = 'plantilla_nominas_' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '_' . $anio . '.xlsx';

        return Excel::download(
            new NominasPlantillaExport($trabajadores, $anio, $mes),
            $nombreArchivo
        );
    }

    /**
     * Procesa el Excel de carga masiva.
     * Regla TODO-O-NADA: si alguna fila tiene un error de validación, NO se crea
     * ninguna nómina. Las filas de trabajadores que ya tienen nómina ese mes/año
     * se omiten (no se pisan). Todo queda registrado en la bitácora.
     */
    public function procesar(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:5120',
        ], [
            'archivo.required' => 'Selecciona un archivo.',
            'archivo.mimes' => 'El archivo debe ser un Excel (.xlsx o .xls).',
            'archivo.max' => 'El archivo no puede superar 5MB.',
        ]);

        $archivo = $request->file('archivo');

        // Leer el Excel a filas posicionales
        $import = new NominasImport();
        Excel::import($import, $archivo);
        $filas = $import->rows;

        // Quitar la fila de encabezados
        array_shift($filas);

        $errores = [];    // errores duros -> bloquean toda la carga
        $aCrear = [];     // filas válidas listas para insertar
        $omitidas = [];   // trabajadores que ya tienen nómina ese mes/año
        $vistos = [];     // claves ya encoladas en este archivo (evita duplicar en memoria)
        $numFila = 1;     // fila 1 = encabezado; los datos empiezan en la 2

        foreach ($filas as $fila) {
            $numFila++;

            // Saltar filas completamente vacías
            $tieneAlgo = collect($fila)->contains(fn ($v) => $v !== null && trim((string) $v) !== '');
            if (!$tieneAlgo) {
                continue;
            }

            $dni = trim((string) ($fila[0] ?? ''));
            $anio = (int) ($fila[3] ?? 0);
            $mes = (int) ($fila[4] ?? 0);
            $bruto = $this->aNumero($fila[5] ?? null);
            $ssEmpresa = $this->aNumero($fila[6] ?? null);
            $ssTrab = $this->aNumero($fila[7] ?? null);
            $irpf = $this->aNumero($fila[8] ?? null);
            $notas = trim((string) ($fila[9] ?? '')) ?: null;

            // Fila de un trabajador sin ningún importe -> se ignora (no tiene nómina ese mes)
            if ($bruto === null && $ssEmpresa === null && $ssTrab === null && $irpf === null) {
                continue;
            }

            $trabajador = Trabajador::where('dni', $dni)->first();

            // Validaciones duras
            if ($dni === '') {
                $errores[] = ['fila' => $numFila, 'dni' => '-', 'motivo' => 'DNI vacío.'];
                continue;
            }
            if (!$trabajador) {
                $errores[] = ['fila' => $numFila, 'dni' => $dni, 'motivo' => 'No existe un trabajador con ese DNI.'];
                continue;
            }
            if ($anio < 2000 || $anio > 2100) {
                $errores[] = ['fila' => $numFila, 'dni' => $dni, 'motivo' => "Año inválido ({$anio})."];
                continue;
            }
            if ($mes < 1 || $mes > 12) {
                $errores[] = ['fila' => $numFila, 'dni' => $dni, 'motivo' => "Mes inválido ({$mes})."];
                continue;
            }
            if ($bruto === null || $bruto < 0) {
                $errores[] = ['fila' => $numFila, 'dni' => $dni, 'motivo' => 'Salario bruto vacío o inválido.'];
                continue;
            }

            // Duplicado: ya existe nómina para ese trabajador/mes/año -> se omite (no se pisa)
            $yaExiste = Nomina::where('trabajador_id', $trabajador->id)
                ->where('anio', $anio)->where('mes', $mes)->exists();
            if ($yaExiste) {
                $omitidas[] = [
                    'fila' => $numFila, 'dni' => $dni,
                    'motivo' => 'Ya tiene nómina en ' . (Nomina::MESES[$mes] ?? $mes) . " {$anio} (se omite).",
                ];
                continue;
            }

            // Deduplicar dentro del MISMO archivo (mismo trabajador/mes/año repetido)
            $clave = $trabajador->id . '|' . $anio . '|' . $mes;
            if (isset($vistos[$clave])) {
                $omitidas[] = [
                    'fila' => $numFila, 'dni' => $dni,
                    'motivo' => 'Fila repetida en el archivo (mismo trabajador y periodo); se omite.',
                ];
                continue;
            }
            $vistos[$clave] = true;

            $aCrear[] = [
                'trabajador_id' => $trabajador->id,
                'anio' => $anio,
                'mes' => $mes,
                'salario_bruto' => $bruto,
                'ss_empresa' => $ssEmpresa ?? 0,
                'ss_trabajador' => $ssTrab ?? 0,
                'irpf' => $irpf ?? 0,
                'liquido' => round($bruto - ($ssTrab ?? 0) - ($irpf ?? 0), 2),
                'notas' => $notas,
            ];
        }

        $totalFilas = count($aCrear) + count($omitidas) + count($errores);

        // TODO-O-NADA: si hay errores duros, no se importa nada. Se registra el intento fallido.
        if (!empty($errores)) {
            NominaImportacion::create([
                'user_id' => auth()->id(),
                'anio' => $filas[0][3] ?? now()->year,
                'mes' => $filas[0][4] ?? now()->month,
                'archivo_nombre' => $archivo->getClientOriginalName(),
                'total_filas' => $totalFilas,
                'creadas' => 0,
                'omitidas' => count($omitidas),
                'con_error' => count($errores),
                'estado' => 'fallida',
                'detalle' => array_merge($errores, $omitidas),
            ]);

            return back()->with('import_error', 'La carga no se realizó: hay ' . count($errores) . ' fila(s) con errores. Corrige el archivo y vuelve a intentarlo.')
                ->with('import_errores', $errores);
        }

        // Guardado en transacción: bitácora + nóminas enlazadas
        DB::transaction(function () use ($request, $archivo, $aCrear, $omitidas, $totalFilas, &$importacion) {
            $importacion = NominaImportacion::create([
                'user_id' => auth()->id(),
                'anio' => $aCrear[0]['anio'] ?? now()->year,
                'mes' => $aCrear[0]['mes'] ?? now()->month,
                'archivo_nombre' => $archivo->getClientOriginalName(),
                'total_filas' => $totalFilas,
                'creadas' => count($aCrear),
                'omitidas' => count($omitidas),
                'con_error' => 0,
                'estado' => empty($omitidas) ? 'procesada' : 'con_omitidas',
                'detalle' => $omitidas,
            ]);

            foreach ($aCrear as $datos) {
                $datos['importacion_id'] = $importacion->id;
                Nomina::create($datos);
            }
        });

        $msg = count($aCrear) . ' nómina(s) creada(s) correctamente.';
        if (!empty($omitidas)) {
            $msg .= ' ' . count($omitidas) . ' omitida(s) por ya existir.';
        }

        return back()->with('import_ok', $msg);
    }

    /**
     * Exporta las nóminas de un mes al MISMO formato del gestoría (re-importable),
     * con todos los trabajadores activos (llenos los que tienen nómina, vacíos los demás).
     */
    public function exportarMes(int $anio, int $mes)
    {
        abort_unless($mes >= 1 && $mes <= 12, 404);

        $ss = (new GestoriaNominaExporter())->spreadsheet($anio, $mes);
        $nombre = 'nominas_' . str_pad((string) $mes, 2, '0', STR_PAD_LEFT) . '_' . $anio . '.xlsx';

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $nombre, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Lista las nóminas de un mes/año concreto (drill-down desde el Resumen).
     */
    public function porMes(int $anio, int $mes)
    {
        abort_unless($mes >= 1 && $mes <= 12, 404);

        $nominas = Nomina::with('trabajador')
            ->where('anio', $anio)->where('mes', $mes)
            ->get()
            ->sortBy(fn ($n) => optional($n->trabajador)->apellidos . ' ' . optional($n->trabajador)->nombre)
            ->values();

        return view('nominas.mes', compact('nominas', 'anio', 'mes'));
    }

    /**
     * Envía por correo el recibo de UNA nómina y la marca como enviada.
     */
    public function enviar(Nomina $nomina)
    {
        $nomina->load('trabajador');
        $email = $nomina->trabajador?->email;

        if (!$email) {
            return back()->with('error', 'El trabajador no tiene correo registrado; no se pudo enviar.');
        }

        try {
            Mail::to($email)->send(new ReciboNominaMail($nomina));
        } catch (\Throwable $e) {
            return back()->with('error', "No se pudo enviar el correo a {$email}. Revisa la configuración de correo.");
        }

        if (!$nomina->enviado_at) {
            $nomina->update(['enviado_at' => now()]);
        }

        return back()->with('success', "Recibo enviado a {$email}.");
    }

    /**
     * Envía por correo todas las nóminas PENDIENTES (no enviadas) de un mes/año.
     */
    public function enviarMes(int $anio, int $mes)
    {
        abort_unless($mes >= 1 && $mes <= 12, 404);

        $nominas = Nomina::with('trabajador')
            ->where('anio', $anio)->where('mes', $mes)
            ->whereNull('enviado_at')->get();

        $enviadas = 0;
        $sinCorreo = 0;
        $fallidos = 0;

        foreach ($nominas as $n) {
            $email = $n->trabajador?->email;
            if (!$email) {
                $sinCorreo++;
                continue;
            }
            try {
                Mail::to($email)->send(new ReciboNominaMail($n));
                $n->update(['enviado_at' => now()]);
                $enviadas++;
            } catch (\Throwable $e) {
                // Un correo fallido no detiene el lote; se contabiliza.
                $fallidos++;
            }
        }

        $msg = "{$enviadas} recibo(s) enviado(s).";
        if ($sinCorreo) {
            $msg .= " {$sinCorreo} sin correo (no enviados).";
        }
        if ($fallidos) {
            $msg .= " {$fallidos} con error de envío.";
        }

        return back()->with('success', $msg);
    }

    /**
     * Procesa el Excel del GESTORÍA (formato transpuesto).
     * Crea una nómina por columna (con su código) + sus conceptos.
     * TODO-O-NADA: si alguna columna no empareja con un trabajador, no se importa nada.
     * El líquido se toma TAL CUAL del archivo (no se recalcula).
     */
    public function procesarGestoria(Request $request)
    {
        $request->validate([
            'archivo' => 'required|file|mimes:xlsx,xls|max:5120',
        ], [
            'archivo.required' => 'Selecciona un archivo.',
            'archivo.mimes' => 'El archivo debe ser un Excel (.xlsx o .xls).',
        ]);

        $archivo = $request->file('archivo');
        try {
            $parsed = (new GestoriaNominaParser())->parse($archivo->getRealPath());
        } catch (\Throwable $e) {
            return back()->with('import_error', 'No se pudo leer el archivo: ' . $e->getMessage());
        }

        $mes = (int) ($parsed['periodo']['mes'] ?? 0);
        $anio = (int) ($parsed['periodo']['anio'] ?? 0);
        if ($mes < 1 || $mes > 12 || $anio < 2000 || $anio > 2100) {
            return back()->with('import_error', "No se pudo determinar el periodo del archivo (mes {$mes}, año {$anio}). Revisa la cabecera del Excel.");
        }

        $matched = (new GestoriaNominaMatcher())->match($parsed['trabajadores']);

        $errores = [];
        $omitidas = [];
        $aCrear = [];
        $vistos = [];

        foreach ($matched as $m) {
            $a = $m['archivo'];
            $t = $m['trabajador'];

            // Columna en blanco (trabajador sin nómina ese mes): se ignora, no es error.
            $tieneDatos = ($a['bruto'] ?? 0) > 0 || ($a['liquido'] ?? 0) > 0 || !empty($a['conceptos']);
            if (!$tieneDatos) {
                continue;
            }

            if (!$t) {
                $motivo = (($m['via'] ?? null) === 'ambiguo')
                    ? 'Coincidencia ambigua: hay varios trabajadores con ese nombre. Revísalo o usa el alias.'
                    : 'No se encontró trabajador en el sistema para este nombre.';
                $errores[] = ['fila' => $a['codigo'], 'dni' => $a['nombre_completo'], 'motivo' => $motivo];
                continue;
            }

            $existe = Nomina::where('trabajador_id', $t->id)
                ->where('anio', $anio)->where('mes', $mes)
                ->where('codigo_nomina', $a['codigo'])->exists();
            if ($existe) {
                $omitidas[] = ['fila' => $a['codigo'], 'dni' => $a['nombre_completo'], 'motivo' => "Ya existe la nómina (código {$a['codigo']}) de este trabajador en el periodo."];
                continue;
            }

            // Deduplicar dentro del MISMO archivo (misma persona + mismo código de línea)
            $clave = $t->id . '|' . ($a['codigo'] ?? '');
            if (isset($vistos[$clave])) {
                $omitidas[] = ['fila' => $a['codigo'], 'dni' => $a['nombre_completo'], 'motivo' => "Fila repetida en el archivo (código {$a['codigo']}); se omite."];
                continue;
            }
            $vistos[$clave] = true;

            $aCrear[] = ['t' => $t, 'a' => $a];
        }

        $totalFilas = count($aCrear) + count($omitidas) + count($errores);

        // TODO-O-NADA: si hay columnas sin emparejar, no se importa nada.
        if (!empty($errores)) {
            NominaImportacion::create([
                'user_id' => auth()->id(),
                'anio' => $anio,
                'mes' => $mes,
                'archivo_nombre' => $archivo->getClientOriginalName(),
                'empresa_razon' => $parsed['empresa']['razon'] ?? null,
                'empresa_nif' => $parsed['empresa']['nif'] ?? null,
                'origen' => 'gestoria',
                'total_filas' => $totalFilas,
                'creadas' => 0,
                'omitidas' => count($omitidas),
                'con_error' => count($errores),
                'estado' => 'fallida',
                'detalle' => array_merge($errores, $omitidas),
            ]);

            return back()->with('import_error', 'La carga no se realizó: hay ' . count($errores) . ' trabajador(es) del archivo sin coincidencia en el sistema. Revisa los nombres o mapéalos.')
                ->with('import_errores', $errores);
        }

        DB::transaction(function () use ($archivo, $parsed, $anio, $mes, $aCrear, $omitidas, $totalFilas) {
            $importacion = NominaImportacion::create([
                'user_id' => auth()->id(),
                'anio' => $anio,
                'mes' => $mes,
                'archivo_nombre' => $archivo->getClientOriginalName(),
                'empresa_razon' => $parsed['empresa']['razon'] ?? null,
                'empresa_nif' => $parsed['empresa']['nif'] ?? null,
                'origen' => 'gestoria',
                'total_filas' => $totalFilas,
                'creadas' => count($aCrear),
                'omitidas' => count($omitidas),
                'con_error' => 0,
                'estado' => empty($omitidas) ? 'procesada' : 'con_omitidas',
                'detalle' => $omitidas,
            ]);

            foreach ($aCrear as $item) {
                $t = $item['t'];
                $a = $item['a'];

                $nomina = Nomina::create([
                    'trabajador_id' => $t->id,
                    'anio' => $anio,
                    'mes' => $mes,
                    'salario_bruto' => $a['bruto'],
                    'ss_empresa' => $a['ss_empresa'],
                    'ss_trabajador' => $a['ss_trabajador'],
                    'irpf' => $a['irpf'],
                    'liquido' => $a['liquido'], // TAL CUAL del archivo, no se recalcula
                    'codigo_nomina' => $a['codigo'],
                    'importacion_id' => $importacion->id,
                ]);

                $orden = 0;
                foreach ($a['conceptos'] as $co) {
                    $nomina->conceptos()->create([
                        'codigo' => $co['codigo'],
                        'concepto' => $co['concepto'],
                        'importe' => $co['importe'],
                        'tipo' => $co['tipo'],
                        'orden' => $orden++,
                    ]);
                }
            }
        });

        $msg = count($aCrear) . ' nómina(s) importada(s) del gestoría (' . (Nomina::MESES[$mes] ?? $mes) . ' ' . $anio . ').';
        if (!empty($omitidas)) {
            $msg .= ' ' . count($omitidas) . ' omitida(s) por ya existir.';
        }

        return back()->with('import_ok', $msg);
    }

    /**
     * Bitácora: listado de todas las cargas masivas realizadas.
     */
    public function bitacora()
    {
        $importaciones = NominaImportacion::with('user')->latest()->take(100)->get();

        return view('nominas.bitacora', compact('importaciones'));
    }

    /**
     * Detalle de una carga: nóminas creadas + filas omitidas/con error.
     */
    public function bitacoraDetalle(NominaImportacion $importacion)
    {
        $importacion->load(['user', 'nominas.trabajador']);

        return view('nominas.bitacora_detalle', compact('importacion'));
    }

    /**
     * Genera y muestra el recibo de nómina en PDF (on-demand, desde los datos).
     * Sirve para cualquier nómina: siempre refleja los importes actuales.
     */
    public function recibo(Nomina $nomina)
    {
        $nomina->load('trabajador', 'conceptos', 'importacion');

        // Autorización: el propio trabajador o un rol de gestión
        $user = auth()->user();
        $esPropia = $nomina->trabajador && $nomina->trabajador->user_id === $user->id;
        if (!$esPropia && !$user->hasAnyRole(['Administrador', 'RRHH', 'Contabilidad'])) {
            abort(403, 'No tienes permiso para ver este recibo.');
        }

        // El trabajador solo puede ver un recibo ya enviado
        if ($esPropia && !$user->hasAnyRole(['Administrador', 'RRHH', 'Contabilidad']) && !$nomina->enviado_at) {
            abort(403, 'Este recibo aún no está disponible.');
        }

        $pdf = Pdf::loadView('nominas.recibo', compact('nomina'));

        $nombre = 'nomina_' . \Illuminate\Support\Str::slug(
            optional($nomina->trabajador)->apellidos . '_' . optional($nomina->trabajador)->nombre
        ) . '_' . $nomina->mes_nombre . '_' . $nomina->anio . '.pdf';

        return $pdf->stream($nombre);
    }

    /**
     * Edita los importes de una nómina (recalcula el líquido).
     * No toca trabajador, mes, año, PDF ni el enlace de importación.
     */
    public function update(Request $request, Nomina $nomina)
    {
        $validated = $request->validate([
            'salario_bruto' => 'required|numeric|min:0',
            'ss_empresa' => 'nullable|numeric|min:0',
            'ss_trabajador' => 'nullable|numeric|min:0',
            'irpf' => 'nullable|numeric|min:0',
            'notas' => 'nullable|string|max:2000',
        ]);

        $bruto = (float) $validated['salario_bruto'];
        $ssTrab = (float) ($validated['ss_trabajador'] ?? 0);
        $irpf = (float) ($validated['irpf'] ?? 0);

        // Si la nómina tiene desglose de conceptos (gestoría), el líquido debe usar
        // TODAS las deducciones (incluye especie), no solo SS+IRPF; recalcular a la
        // simple corromería el importe. Para nóminas manuales (sin conceptos) sí aplica.
        $nomina->loadMissing('conceptos');
        if ($nomina->conceptos->isNotEmpty()) {
            $totalDeducciones = (float) $nomina->conceptos->where('tipo', 'deduccion')->sum('importe');
            $liquido = round($bruto - $totalDeducciones, 2);
        } else {
            $liquido = round($bruto - $ssTrab - $irpf, 2);
        }

        $nomina->update([
            'salario_bruto' => $bruto,
            'ss_empresa' => (float) ($validated['ss_empresa'] ?? 0),
            'ss_trabajador' => $ssTrab,
            'irpf' => $irpf,
            'liquido' => $liquido,
            'notas' => $validated['notas'] ?? null,
        ]);

        return back()->with('success', 'Nómina actualizada correctamente.');
    }

    /**
     * Convierte un valor de celda a número (float) o null si está vacío/no es numérico.
     * Acepta formato europeo "1.234,56".
     */
    private function aNumero($valor): ?float
    {
        if ($valor === null) {
            return null;
        }
        // Números nativos de la hoja (no texto): usar tal cual, sin reinterpretar.
        if (is_int($valor) || is_float($valor)) {
            return (float) $valor;
        }
        $s = trim((string) $valor);
        if ($s === '') {
            return null;
        }
        // Texto en formato europeo: '.' = separador de miles, ',' = decimal.
        $s = preg_replace('/[\s\x{00A0}]/u', '', $s); // quita espacios, incl. el duro (NBSP)
        $s = str_replace('.', '', $s);                 // separador de miles
        $s = str_replace(',', '.', $s);                // coma decimal
        return is_numeric($s) ? (float) $s : null;
    }
}
