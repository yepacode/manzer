<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Plantilla de autollenado para la carga masiva de nóminas.
 * Genera una fila por trabajador con DNI/Nombre/Apellidos de referencia
 * y las columnas de importes en blanco para rellenar.
 */
class NominasPlantillaExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    protected $trabajadores;
    protected int $anio;
    protected int $mes;

    public function __construct($trabajadores, int $anio, int $mes)
    {
        $this->trabajadores = $trabajadores;
        $this->anio = $anio;
        $this->mes = $mes;
    }

    public function collection()
    {
        return $this->trabajadores;
    }

    public function headings(): array
    {
        return [
            'DNI',            // referencia (no editar) — se usa para emparejar
            'Nombre',         // referencia (no editar)
            'Apellidos',      // referencia (no editar)
            'Año',
            'Mes',
            'Salario Bruto',
            'SS Empresa',
            'SS Trabajador',
            'IRPF',
            'Notas',
        ];
    }

    public function map($trabajador): array
    {
        return [
            $trabajador->dni,
            $trabajador->nombre,
            $trabajador->apellidos,
            $this->anio,
            $this->mes,
            null,   // Salario Bruto — a rellenar
            null,   // SS Empresa
            null,   // SS Trabajador
            null,   // IRPF
            null,   // Notas
        ];
    }

    public function styles(Worksheet $sheet)
    {
        // Encabezado en negrita
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function title(): string
    {
        return 'Nóminas ' . $this->mes . '-' . $this->anio;
    }
}
