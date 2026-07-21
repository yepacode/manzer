<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;

/**
 * Export genérico a partir de una grilla (array 2D) ya construida.
 */
class GridArrayExport implements FromArray
{
    public function __construct(private array $grid)
    {
    }

    public function array(): array
    {
        return $this->grid;
    }
}
