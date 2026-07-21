<?php

namespace App\Http\Controllers;

use App\Models\Nomina;
use App\Models\Trabajador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class NominaController extends Controller
{
    /**
     * Guardar una nómina de un trabajador (admin/RRHH).
     */
    public function store(Request $request, Trabajador $trabajador)
    {
        $validated = $request->validate([
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'salario_bruto' => 'required|numeric|min:0',
            'ss_empresa' => 'nullable|numeric|min:0',
            'ss_trabajador' => 'nullable|numeric|min:0',
            'irpf' => 'nullable|numeric|min:0',
            'documento' => 'nullable|file|mimes:pdf|max:5120',
            'notas' => 'nullable|string|max:2000',
        ], [
            'documento.mimes' => 'La nómina debe ser un PDF.',
            'documento.max' => 'El PDF no puede superar 5MB.',
        ]);

        if (Nomina::where('trabajador_id', $trabajador->id)->where('anio', $validated['anio'])->where('mes', $validated['mes'])->exists()) {
            return back()->with('error', 'Ya existe una nómina para ese trabajador en ese mes/año.');
        }

        $bruto = (float) $validated['salario_bruto'];
        $ssTrab = (float) ($validated['ss_trabajador'] ?? 0);
        $irpf = (float) ($validated['irpf'] ?? 0);

        $data = [
            'trabajador_id' => $trabajador->id,
            'anio' => $validated['anio'],
            'mes' => $validated['mes'],
            'salario_bruto' => $bruto,
            'ss_empresa' => (float) ($validated['ss_empresa'] ?? 0),
            'ss_trabajador' => $ssTrab,
            'irpf' => $irpf,
            'liquido' => round($bruto - $ssTrab - $irpf, 2),
            'notas' => $validated['notas'] ?? null,
        ];

        if ($request->hasFile('documento')) {
            $archivo = $request->file('documento');
            $nombre = 'nomina_' . $validated['anio'] . '_' . $validated['mes'] . '_' . time() . '.' . $archivo->getClientOriginalExtension();
            // Disco privado (storage/app), NO accesible por URL directa; se sirve solo por download().
            $data['documento_path'] = $archivo->storeAs('private/nominas/' . $trabajador->id, $nombre);
        }

        Nomina::create($data);

        return back()->with('success', 'Nómina registrada correctamente.');
    }

    /**
     * Alta centralizada de nómina desde el Resumen: se elige el trabajador en el formulario.
     */
    public function storeCentral(Request $request)
    {
        $validated = $request->validate([
            'trabajador_id' => 'required|exists:trabajadores,id',
            'anio' => 'required|integer|min:2000|max:2100',
            'mes' => 'required|integer|min:1|max:12',
            'salario_bruto' => 'required|numeric|min:0',
            'ss_empresa' => 'nullable|numeric|min:0',
            'ss_trabajador' => 'nullable|numeric|min:0',
            'irpf' => 'nullable|numeric|min:0',
            'documento' => 'nullable|file|mimes:pdf|max:5120',
            'notas' => 'nullable|string|max:2000',
        ], [
            'trabajador_id.required' => 'Selecciona un trabajador.',
            'documento.mimes' => 'La nómina debe ser un PDF.',
            'documento.max' => 'El PDF no puede superar 5MB.',
        ]);

        $trabajador = Trabajador::findOrFail($validated['trabajador_id']);

        if (Nomina::where('trabajador_id', $trabajador->id)->where('anio', $validated['anio'])->where('mes', $validated['mes'])->exists()) {
            return back()->with('error', 'Ya existe una nómina para ' . $trabajador->nombre . ' ' . $trabajador->apellidos . ' en ' . Nomina::MESES[$validated['mes']] . ' de ' . $validated['anio'] . '.')->withInput();
        }

        $bruto = (float) $validated['salario_bruto'];
        $ssTrab = (float) ($validated['ss_trabajador'] ?? 0);
        $irpf = (float) ($validated['irpf'] ?? 0);

        $data = [
            'trabajador_id' => $trabajador->id,
            'anio' => $validated['anio'],
            'mes' => $validated['mes'],
            'salario_bruto' => $bruto,
            'ss_empresa' => (float) ($validated['ss_empresa'] ?? 0),
            'ss_trabajador' => $ssTrab,
            'irpf' => $irpf,
            'liquido' => round($bruto - $ssTrab - $irpf, 2),
            'notas' => $validated['notas'] ?? null,
        ];

        if ($request->hasFile('documento')) {
            $archivo = $request->file('documento');
            $nombre = 'nomina_' . $validated['anio'] . '_' . $validated['mes'] . '_' . time() . '.' . $archivo->getClientOriginalExtension();
            // Disco privado (storage/app), NO accesible por URL directa; se sirve solo por download().
            $data['documento_path'] = $archivo->storeAs('private/nominas/' . $trabajador->id, $nombre);
        }

        Nomina::create($data);

        return redirect()->route('nominas.resumen', ['anio' => $validated['anio']])
            ->with('success', 'Nómina de ' . $trabajador->nombre . ' ' . $trabajador->apellidos . ' (' . Nomina::MESES[$validated['mes']] . ' ' . $validated['anio'] . ') registrada correctamente.');
    }

    /**
     * Eliminar una nómina.
     */
    public function destroy(Nomina $nomina)
    {
        if ($nomina->documento_path) {
            if (Storage::disk('local')->exists($nomina->documento_path)) {
                Storage::disk('local')->delete($nomina->documento_path);
            } elseif (file_exists(public_path($nomina->documento_path))) {
                @unlink(public_path($nomina->documento_path));
            }
        }
        $nomina->delete();

        return back()->with('success', 'Nómina eliminada correctamente.');
    }

    /**
     * Descargar el PDF de una nómina (admin/RRHH/Contabilidad o el propio trabajador).
     */
    public function download(Nomina $nomina)
    {
        $user = auth()->user();
        $miTrabajador = Trabajador::where('user_id', $user->id)->first();
        $esPropia = $miTrabajador && $miTrabajador->id === $nomina->trabajador_id;

        $esGestion = $user->hasAnyRole(['Administrador', 'RRHH', 'Contabilidad']);
        if (!$esPropia && !$esGestion) {
            abort(403, 'No tienes permiso para descargar esta nómina.');
        }
        // El trabajador solo puede descargar su recibo si ya fue enviado (igual que recibo()).
        if ($esPropia && !$esGestion && !$nomina->enviado_at) {
            abort(403, 'Este recibo aún no está disponible.');
        }

        $nombreDescarga = 'nomina_' . $nomina->mes_nombre . '_' . $nomina->anio . '.pdf';
        $path = $nomina->documento_path;

        if ($path && Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->download($path, $nombreDescarga);
        }
        // Compatibilidad con archivos antiguos guardados bajo public/
        if ($path && file_exists(public_path($path))) {
            return response()->download(public_path($path), $nombreDescarga);
        }

        abort(404, 'No hay documento adjunto en esta nómina.');
    }

    /**
     * Resumen mensual de nóminas (coste empresa, SS, IRPF, líquido).
     */
    public function resumen(Request $request)
    {
        $anio = (int) $request->input('anio', now()->year);

        $nominas = Nomina::where('anio', $anio)->get();

        $porMes = [];
        foreach (range(1, 12) as $m) {
            $delMes = $nominas->where('mes', $m);
            if ($delMes->isEmpty()) {
                continue;
            }
            $bruto = (float) $delMes->sum('salario_bruto');
            $ssEmp = (float) $delMes->sum('ss_empresa');
            $porMes[$m] = [
                'nombre' => Nomina::MESES[$m],
                'bruto' => $bruto,
                'ss_empresa' => $ssEmp,
                'ss_trabajador' => (float) $delMes->sum('ss_trabajador'),
                'irpf' => (float) $delMes->sum('irpf'),
                'liquido' => (float) $delMes->sum('liquido'),
                'coste_empresa' => $bruto + $ssEmp,
                'count' => $delMes->count(),
            ];
        }

        $totales = [
            'bruto' => (float) $nominas->sum('salario_bruto'),
            'ss_empresa' => (float) $nominas->sum('ss_empresa'),
            'ss_trabajador' => (float) $nominas->sum('ss_trabajador'),
            'irpf' => (float) $nominas->sum('irpf'),
            'liquido' => (float) $nominas->sum('liquido'),
            'coste_empresa' => (float) $nominas->sum('salario_bruto') + (float) $nominas->sum('ss_empresa'),
        ];

        $anios = range(now()->year, now()->year - 5);

        // Trabajadores activos para el alta centralizada de nóminas
        $trabajadores = Trabajador::where('activo', true)
            ->orderBy('apellidos')->orderBy('nombre')
            ->get();

        return view('nominas.resumen', compact('anio', 'porMes', 'totales', 'anios', 'trabajadores'));
    }

    /**
     * Portal del trabajador: mis nóminas.
     */
    public function misNominas()
    {
        $trabajador = Trabajador::where('user_id', auth()->id())->first();
        abort_unless($trabajador, 403, 'No tienes un perfil de trabajador asociado.');

        $nominas = Nomina::where('trabajador_id', $trabajador->id)
            ->orderByDesc('anio')->orderByDesc('mes')->get();

        return view('trabajador.nominas', compact('nominas', 'trabajador'));
    }
}
