<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Lee el Excel de nóminas del gestoría (formato transpuesto: trabajadores en columnas,
 * conceptos en filas) y devuelve una estructura normalizada. NO guarda nada.
 *
 * Estructura devuelta:
 * [
 *   'periodo'  => ['mes' => 6, 'anio' => 2026],
 *   'empresa'  => ['razon' => '169-ANOU AUDIOVISUALS, S.L.', 'nif' => 'B64606502'],
 *   'trabajadores' => [
 *       ['codigo','apellidos','nombre','nombre_completo',
 *        'bruto','ss_empresa','ss_trabajador','irpf','liquido',
 *        'conceptos' => [ ['codigo','concepto','importe','tipo'], ... ] ],
 *       ...
 *   ],
 * ]
 */
class GestoriaNominaParser
{
    public function parse(string $filePath): array
    {
        $ss = IOFactory::load($filePath);
        // La hoja de datos es la primera (suele llamarse "1"); la 2ª son solo totales.
        $sheet = $ss->getSheet(0);

        $maxRow = $sheet->getHighestRow();
        $maxCol = Coordinate::columnIndexFromString($sheet->getHighestColumn());

        $periodo = $this->parsePeriodo($this->cell($sheet, 1, 4));   // A4
        $empresa = $this->parseEmpresa($this->cell($sheet, 1, 5));   // A5

        // Mapa de etiquetas de TOTALES/BASES (columna A) -> nº de fila
        $labels = [];
        for ($r = 1; $r <= $maxRow; $r++) {
            $lbl = $this->norm($this->cell($sheet, 1, $r));
            if ($lbl !== '') {
                $labels[$lbl] = $r;
            }
        }
        $rowBruto = $labels[$this->norm('TOTAL BRUTO')] ?? null;
        if ($rowBruto === null) {
            // Sin esta fila no se puede separar devengos de deducciones ni leer el bruto.
            throw new \RuntimeException('El archivo no tiene la fila "TOTAL BRUTO"; no parece el formato del gestoría.');
        }

        // Filas de CONCEPTO (código en col B + nombre en col C)
        $conceptRows = [];
        for ($r = 1; $r <= $maxRow; $r++) {
            $cod = trim((string) $this->cell($sheet, 2, $r));
            $con = trim((string) $this->cell($sheet, 3, $r));
            if ($con !== '' && $cod !== '') {
                $conceptRows[] = ['row' => $r, 'codigo' => $cod, 'concepto' => $con];
            }
        }

        // Filas de BASE (etiqueta en col A que empieza por "BASE")
        $baseRows = [];
        foreach ($labels as $lbl => $r) {
            if (strpos($lbl, 'BASE') === 0) {
                $baseRows[] = ['row' => $r, 'concepto' => $this->cell($sheet, 1, $r)];
            }
        }

        $trabajadores = [];
        for ($c = 4; $c <= $maxCol; $c++) {
            $codigo = trim((string) $this->cell($sheet, $c, 4));
            $ape1 = trim((string) $this->cell($sheet, $c, 5));
            $ape2 = trim((string) $this->cell($sheet, $c, 6));
            $nom = trim((string) $this->cell($sheet, $c, 7));

            // Es columna de trabajador si tiene código O nombre (las descargadas en
            // blanco no traen código pero sí el nombre, para poder rellenarlas).
            if ($codigo === '' && $ape1 === '' && $ape2 === '' && $nom === '') {
                continue;
            }

            $apellidos = trim($ape1 . ' ' . $ape2);

            // Conceptos del trabajador (devengo si va antes de TOTAL BRUTO, si no deducción)
            $conceptos = [];
            $irpf = 0.0;
            foreach ($conceptRows as $cr) {
                $imp = $this->num($this->cell($sheet, $c, $cr['row']));
                if ($imp === null || $imp == 0.0) {
                    continue; // solo guardamos conceptos con importe
                }
                $tipo = ($rowBruto && $cr['row'] < $rowBruto) ? 'devengo' : 'deduccion';
                $conceptos[] = [
                    'codigo' => $cr['codigo'],
                    'concepto' => $cr['concepto'],
                    'importe' => $imp,
                    'tipo' => $tipo,
                ];
                // IRPF: por código 999 o por el texto del concepto (I.R.P.F. / IRPF)
                $conNorm = $this->norm($cr['concepto']);
                if ($cr['codigo'] === '999' || strpos($conNorm, 'IRPF') !== false || strpos($conNorm, 'I R P F') !== false) {
                    $irpf = $imp;
                }
            }
            // Bases
            foreach ($baseRows as $br) {
                $imp = $this->num($this->cell($sheet, $c, $br['row']));
                if ($imp === null) {
                    continue;
                }
                $conceptos[] = [
                    'codigo' => null,
                    'concepto' => trim((string) $br['concepto']),
                    'importe' => $imp,
                    'tipo' => 'base',
                ];
            }

            $trabajadores[] = [
                'codigo' => $codigo,
                'apellidos' => $apellidos,
                'nombre' => $nom,
                'nombre_completo' => trim($apellidos . ' ' . $nom),
                'bruto' => $this->totalCol($sheet, $c, $labels, 'TOTAL BRUTO'),
                'ss_empresa' => $this->totalCol($sheet, $c, $labels, 'SEGURIDAD SOCIAL EMPRESA'),
                'ss_trabajador' => $this->totalCol($sheet, $c, $labels, 'SEGURIDAD SOCIAL TRABAJADOR'),
                'irpf' => $irpf,
                'liquido' => $this->totalCol($sheet, $c, $labels, 'TOTAL LIQUIDO'),
                'conceptos' => $conceptos,
            ];
        }

        return [
            'periodo' => $periodo,
            'empresa' => $empresa,
            'trabajadores' => $trabajadores,
        ];
    }

    private function totalCol($sheet, int $col, array $labels, string $label): float
    {
        $r = $labels[$this->norm($label)] ?? null;
        if (!$r) {
            return 0.0;
        }
        return $this->num($this->cell($sheet, $col, $r)) ?? 0.0;
    }

    private function parsePeriodo(?string $texto): array
    {
        // "DEL 01/06/26 AL 30/06/26" -> mes 6, año 2026
        if ($texto && preg_match('#(\d{2})/(\d{2})/(\d{2,4})#', $texto, $m)) {
            $anio = (int) $m[3];
            if ($anio < 100) {
                $anio += 2000;
            }
            return ['mes' => (int) $m[2], 'anio' => $anio];
        }
        return ['mes' => (int) now()->month, 'anio' => (int) now()->year];
    }

    private function parseEmpresa(?string $texto): array
    {
        $razon = '';
        $nif = '';
        if ($texto) {
            $texto = trim(preg_replace('/^\s*Empresa:\s*/i', '', $texto));
            if (preg_match('/^(.*?)\s*NIF:\s*([A-Z0-9]+)/i', $texto, $m)) {
                $razon = trim($m[1]);
                $nif = trim($m[2]);
            } else {
                $razon = $texto;
            }
        }
        return ['razon' => $razon, 'nif' => $nif];
    }

    private function cell($sheet, int $col, int $row)
    {
        return $sheet->getCellByColumnAndRow($col, $row)->getCalculatedValue();
    }

    private function num($valor): ?float
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

    private function norm($s): string
    {
        $s = mb_strtoupper(trim((string) $s));
        $s = strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U']);
        $s = preg_replace('/[^A-Z0-9 ]/', ' ', $s);
        return trim(preg_replace('/\s+/', ' ', $s));
    }
}
