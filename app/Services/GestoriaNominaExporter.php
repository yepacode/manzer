<?php

namespace App\Services;

use App\Models\Nomina;
use App\Models\NominaConcepto;
use App\Models\Trabajador;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Genera la grilla (array 2D) de un mes en el MISMO formato del gestoría
 * (trabajadores en columnas, conceptos en filas). Se rellena con las nóminas
 * que existen y deja en blanco los trabajadores activos sin nómina.
 * El resultado es re-importable por GestoriaNominaParser (ciclo bajar→llenar→subir).
 */
class GestoriaNominaExporter
{
    public function grid(int $anio, int $mes): array
    {
        $nominas = Nomina::with(['conceptos', 'trabajador', 'importacion'])
            ->where('anio', $anio)->where('mes', $mes)
            ->get()
            ->sortBy(fn ($n) => optional($n->trabajador)->apellidos . ' ' . optional($n->trabajador)->nombre)
            ->values();

        // Columnas: una por nómina existente + una en blanco por trabajador activo sin nómina
        $columnas = [];
        foreach ($nominas as $n) {
            $columnas[] = ['nomina' => $n, 'trabajador' => $n->trabajador];
        }
        $conNomina = $nominas->pluck('trabajador_id')->unique();
        $activosSin = Trabajador::where('activo', true)
            ->whereNotIn('id', $conNomina->all())
            ->orderBy('apellidos')->orderBy('nombre')->get();
        foreach ($activosSin as $t) {
            $columnas[] = ['nomina' => null, 'trabajador' => $t];
        }

        // Conceptos estándar (siempre presentes, aunque el mes esté vacío, para poder rellenar)
        $devengos = $this->conceptosCanonicos('devengo');
        $deducciones = $this->conceptosCanonicos('deduccion');
        $bases = $this->conceptosCanonicos('base');

        // Empresa y periodo (de la importación si existe, si no valores por defecto)
        $imp = $nominas->pluck('importacion')->filter()->first();
        $razon = $imp->empresa_razon ?? 'MANZER Agroforestal, S.L.R.U.';
        $nif = $imp->empresa_nif ?? '';
        $ini = Carbon::createFromDate($anio, $mes, 1);
        $fin = $ini->copy()->endOfMonth();
        $periodo = 'DEL ' . $ini->format('d/m/y') . ' AL ' . $fin->format('d/m/y');

        $nc = count($columnas);
        $blank = fn () => array_fill(0, 3 + $nc, '');

        $rows = [];
        // Fila 1 (grilla) vacía
        $rows[] = $blank();
        // Fila 2: Moneda
        $r = $blank(); $r[0] = 'Moneda: Euro'; $rows[] = $r;
        // Fila 3: tipo de paga
        $r = $blank(); $r[0] = 'PAGA MENSUAL'; $rows[] = $r;
        // Fila 4: periodo (A) + códigos de nómina (D+)
        $r = $blank(); $r[0] = $periodo;
        foreach ($columnas as $i => $c) { $r[3 + $i] = optional($c['nomina'])->codigo_nomina ?? ''; }
        $rows[] = $r;
        // Fila 5: empresa (A) + apellido1 (D+)
        $r = $blank(); $r[0] = 'Empresa: ' . $razon . ($nif ? ' NIF: ' . $nif : '');
        foreach ($columnas as $i => $c) { $r[3 + $i] = $this->apellido1($c['trabajador']); }
        $rows[] = $r;
        // Fila 6: apellido2
        $r = $blank();
        foreach ($columnas as $i => $c) { $r[3 + $i] = $this->apellido2($c['trabajador']); }
        $rows[] = $r;
        // Fila 7: nombre
        $r = $blank();
        foreach ($columnas as $i => $c) { $r[3 + $i] = optional($c['trabajador'])->nombre; }
        $rows[] = $r;
        // Fila 8 vacía
        $rows[] = $blank();
        // Fila 9: CONCEPTO
        $r = $blank(); $r[0] = 'CONCEPTO'; $rows[] = $r;
        // Fila 10 vacía
        $rows[] = $blank();

        // Devengos
        foreach ($devengos as $c) {
            $rows[] = $this->filaConcepto($c, $columnas, $blank);
        }
        // TOTAL BRUTO
        $rows[] = $this->filaTotal('TOTAL BRUTO', $columnas, $blank, fn ($n) => $n->salario_bruto);
        // Deducciones
        foreach ($deducciones as $c) {
            $rows[] = $this->filaConcepto($c, $columnas, $blank);
        }
        // Totales
        $rows[] = $this->filaTotal('TOTAL DEDUCCIONES', $columnas, $blank, fn ($n) => round($n->salario_bruto - $n->liquido, 2));
        $rows[] = $this->filaTotal('TOTAL LIQUIDO', $columnas, $blank, fn ($n) => $n->liquido);
        $rows[] = $this->filaTotal('SEGURIDAD SOCIAL EMPRESA', $columnas, $blank, fn ($n) => $n->ss_empresa);
        $rows[] = $this->filaTotal('SEGURIDAD SOCIAL TRABAJADOR', $columnas, $blank, fn ($n) => $n->ss_trabajador);
        // Bases
        foreach ($bases as $c) {
            $rows[] = $this->filaBase($c, $columnas, $blank);
        }

        return $rows;
    }

    /**
     * Igual que grid() pero devuelve un Spreadsheet CON ESTILO (colores suaves,
     * encabezados, bordes, totales resaltados). Sigue siendo re-importable.
     */
    public function spreadsheet(int $anio, int $mes): Spreadsheet
    {
        $grid = $this->grid($anio, $mes);
        $numRows = count($grid);
        $numCols = 0;
        foreach ($grid as $r) {
            $numCols = max($numCols, count($r));
        }

        $VERDE = '2E7D32';
        $VERDE_CLARO = 'E8F1E9';
        $GRIS = 'EEF1F3';
        $GRIS_CLARO = 'F8F9FA';
        $ROJO_CLARO = 'FCEBEA';

        $ss = new Spreadsheet();
        $sheet = $ss->getActiveSheet();
        $sheet->setTitle('Nóminas ' . $mes . '-' . $anio);
        $sheet->setShowGridlines(false);

        // Volcar datos (grid 0-based -> Excel 1-based)
        foreach ($grid as $i => $fila) {
            foreach ($fila as $j => $val) {
                if ($val !== '' && $val !== null) {
                    $sheet->setCellValueByColumnAndRow($j + 1, $i + 1, $val);
                }
            }
        }

        $lastCol = Coordinate::stringFromColumnIndex($numCols);
        $razon = trim(preg_replace('/\s*NIF:.*$/', '', preg_replace('/^Empresa:\s*/', '', (string) ($grid[4][0] ?? 'Nóminas'))));
        $mesNombre = Nomina::MESES[$mes] ?? $mes;

        // Anchos de columna
        $sheet->getColumnDimension('A')->setWidth(30);
        $sheet->getColumnDimension('B')->setWidth(6);
        $sheet->getColumnDimension('C')->setWidth(26);
        for ($c = 4; $c <= $numCols; $c++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($c))->setWidth(13);
        }

        // Banner de título en la fila 1 (el parser ignora esta fila)
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->setCellValue('A1', mb_strtoupper($razon) . '   ·   NÓMINAS ' . mb_strtoupper($mesNombre) . ' ' . $anio);
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle('A1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($VERDE);
        $sheet->getStyle('A1')->getAlignment()->setVertical(Alignment::VERTICAL_CENTER)->setIndent(1);
        $sheet->getRowDimension(1)->setRowHeight(30);

        // Meta (filas 2-4, columna A) en gris pequeño
        $sheet->getStyle('A2:A4')->getFont()->setSize(9)->getColor()->setRGB('777777');
        $sheet->getStyle('A5')->getFont()->setSize(9)->getColor()->setRGB('777777');

        // Encabezado de trabajadores (filas 4-7, columnas D+): fondo gris, negrita, centrado
        $rangoCab = 'D4:' . $lastCol . '7';
        $sheet->getStyle($rangoCab)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($GRIS);
        $sheet->getStyle($rangoCab)->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle($rangoCab)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
        $sheet->getStyle($rangoCab)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CCCCCC');
        $sheet->getRowDimension(5)->setRowHeight(26);
        $sheet->getRowDimension(6)->setRowHeight(26);

        // CONCEPTO (fila 9)
        $sheet->getStyle('A9')->getFont()->setBold(true)->setSize(11)->getColor()->setRGB($VERDE);

        // Recorrer filas para pintar conceptos, totales y bases según la etiqueta de col A
        for ($i = 10; $i < $numRows; $i++) {
            $excelRow = $i + 1;
            $labelA = mb_strtoupper(trim((string) ($grid[$i][0] ?? '')));
            $rango = "A{$excelRow}:{$lastCol}{$excelRow}";
            $rangoNum = "D{$excelRow}:{$lastCol}{$excelRow}";

            // Formato de número a los importes
            $sheet->getStyle($rangoNum)->getNumberFormat()->setFormatCode('#,##0.00');
            $sheet->getStyle($rangoNum)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            if ($labelA === 'TOTAL BRUTO') {
                $this->pintar($sheet, $rango, $VERDE_CLARO, true);
            } elseif ($labelA === 'TOTAL DEDUCCIONES') {
                $this->pintar($sheet, $rango, $ROJO_CLARO, true);
            } elseif ($labelA === 'TOTAL LIQUIDO') {
                $sheet->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($VERDE);
                $sheet->getStyle($rango)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
            } elseif (strpos($labelA, 'SEGURIDAD SOCIAL') === 0) {
                $this->pintar($sheet, $rango, $GRIS, false);
            } elseif (strpos($labelA, 'BASE') === 0) {
                $this->pintar($sheet, $rango, $GRIS_CLARO, false);
                $sheet->getStyle("A{$excelRow}")->getFont()->setSize(9)->getColor()->setRGB('888888');
            }
        }

        // Bordes suaves alrededor de toda la tabla de datos
        $sheet->getStyle("A9:{$lastCol}" . $numRows)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('E0E0E0');

        // Congelar encabezados (filas 1-9) y columnas A-C
        $sheet->freezePane('D10');

        return $ss;
    }

    private function pintar($sheet, string $rango, string $color, bool $bold): void
    {
        $sheet->getStyle($rango)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($color);
        if ($bold) {
            $sheet->getStyle($rango)->getFont()->setBold(true);
        }
    }

    /**
     * Lista de conceptos que SIEMPRE aparecen en la plantilla (para poder rellenar
     * meses vacíos). Se toma de los conceptos que ya usa la empresa; si aún no hay
     * ninguno, se usa una lista estándar del gestoría.
     */
    private function conceptosCanonicos(string $tipo): array
    {
        $rows = NominaConcepto::where('tipo', $tipo)
            ->selectRaw('codigo, concepto, MIN(orden) as orden')
            ->groupBy('codigo', 'concepto')
            ->orderBy('orden')
            ->get();

        if ($rows->isNotEmpty()) {
            return $rows->map(fn ($r) => ['codigo' => $r->codigo, 'concepto' => $r->concepto, 'orden' => (int) $r->orden])->all();
        }

        // Lista estándar (fallback) si la BD aún no tiene conceptos
        $estandar = [
            'devengo' => [
                ['1', 'Salario Base'], ['33', 'Complemento a Líquido'], ['129', 'Prorrata Pagas Extras'],
                ['141', 'Ret.Especie'], ['199', 'Parte proporcional vacaciones'], ['243', 'Mejora Voluntaria Absorbible'],
                ['988', 'Imp.Ingr. a Cta. Especie'],
            ],
            'deduccion' => [
                ['995', 'Cotización Contingencias Comunes'], ['996', 'Cotización Formación Profesional'],
                ['997', 'Cotización Desempleo'], ['999', 'TRIBUTACION I.R.P.F.'],
                ['789', 'Dcto. Conceptos en Especie'], ['989', 'Imp.Ingr. a Cta. Valores Especie'],
            ],
            'base' => [
                [null, 'BASE CONTINGENCIAS COMUNES'], [null, 'BASE ACCIDENTES'], [null, 'BASE I.R.P.F. DINERARIA'],
                [null, 'BASE I.R.P.F. ESPECIES'], [null, 'BASE I.R.P.F. IRREGULAR'],
            ],
        ];

        $out = [];
        foreach ($estandar[$tipo] ?? [] as $i => [$cod, $nom]) {
            $out[] = ['codigo' => $cod, 'concepto' => $nom, 'orden' => $i];
        }
        return $out;
    }

    private function filaConcepto(array $c, array $columnas, $blank): array
    {
        $r = $blank();
        $r[1] = $c['codigo'];
        $r[2] = $c['concepto'];
        foreach ($columnas as $i => $col) {
            $n = $col['nomina'];
            if ($n) {
                $match = $n->conceptos->first(fn ($x) => ($x->codigo ?? '') === ($c['codigo'] ?? '') && $x->concepto === $c['concepto']);
                $r[3 + $i] = $match ? (float) $match->importe : '';
            }
        }
        return $r;
    }

    private function filaBase(array $c, array $columnas, $blank): array
    {
        $r = $blank();
        $r[0] = $c['concepto']; // las bases van con etiqueta en col A
        foreach ($columnas as $i => $col) {
            $n = $col['nomina'];
            if ($n) {
                $match = $n->conceptos->first(fn ($x) => $x->tipo === 'base' && $x->concepto === $c['concepto']);
                $r[3 + $i] = $match ? (float) $match->importe : '';
            }
        }
        return $r;
    }

    private function filaTotal(string $label, array $columnas, $blank, callable $valor): array
    {
        $r = $blank();
        $r[0] = $label;
        foreach ($columnas as $i => $col) {
            $n = $col['nomina'];
            $r[3 + $i] = $n ? (float) $valor($n) : '';
        }
        return $r;
    }

    private function apellido1($t): string
    {
        $parts = preg_split('/\s+/', trim((string) optional($t)->apellidos));
        return $parts[0] ?? '';
    }

    private function apellido2($t): string
    {
        $parts = preg_split('/\s+/', trim((string) optional($t)->apellidos));
        return trim(implode(' ', array_slice($parts, 1)));
    }
}
