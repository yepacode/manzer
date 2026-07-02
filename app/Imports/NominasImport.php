<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToArray;

/**
 * Lee la plantilla de nóminas a un array posicional de filas.
 * La validación y el guardado se hacen en NominaImportacionController::procesar
 * para tener control total (todo-o-nada).
 */
class NominasImport implements ToArray
{
    public array $rows = [];

    public function array(array $array): void
    {
        $this->rows = $array;
    }
}
