<?php

namespace App\Services;

use App\Models\Trabajador;

/**
 * Empareja cada trabajador leído del archivo del gestoría con un Trabajador del sistema,
 * por nombre normalizado (o por su alias_nomina). No guarda nada: solo resuelve el match.
 */
class GestoriaNominaMatcher
{
    /** @var array<int, array{trab: Trabajador, tokens: array}> */
    private array $indice = [];

    public function __construct()
    {
        foreach (Trabajador::select('id', 'nombre', 'apellidos', 'dni', 'alias_nomina')->get() as $t) {
            // Un trabajador puede casar por su nombre real o por su alias del gestoría
            $claves = [$t->apellidos . ' ' . $t->nombre];
            if ($t->alias_nomina) {
                $claves[] = $t->alias_nomina;
            }
            foreach ($claves as $clave) {
                $tokens = $this->tokens($clave);
                if (!empty($tokens)) {
                    $this->indice[] = ['trab' => $t, 'tokens' => $tokens];
                }
            }
        }
    }

    /**
     * @param  array  $trabajadoresArchivo  la clave 'trabajadores' de GestoriaNominaParser::parse()
     * @return array lista con ['archivo'=>..., 'trabajador'=>Trabajador|null, 'via'=>'nombre'|null]
     */
    public function match(array $trabajadoresArchivo): array
    {
        $resultado = [];
        foreach ($trabajadoresArchivo as $fila) {
            $fileTokens = $this->tokens($fila['nombre_completo']);

            // Candidatos que superan el umbral (>=2 tokens compartidos y cobertura >=0.6)
            $candidatos = [];
            foreach ($this->indice as $cand) {
                $inter = count(array_intersect($fileTokens, $cand['tokens']));
                if ($inter < 2) {
                    continue;
                }
                $cobertura = $inter / max(1, min(count($fileTokens), count($cand['tokens'])));
                if ($cobertura < 0.6) {
                    continue;
                }
                $candidatos[] = ['trab' => $cand['trab'], 'score' => $inter + $cobertura];
            }

            usort($candidatos, fn ($a, $b) => $b['score'] <=> $a['score']);

            $trabajador = null;
            $via = null;
            if (!empty($candidatos)) {
                $mejor = $candidatos[0];
                // ¿Hay OTRO trabajador distinto con prácticamente el mismo score? -> ambiguo
                $ambiguo = false;
                foreach ($candidatos as $c) {
                    if ($c['trab']->id !== $mejor['trab']->id && abs($c['score'] - $mejor['score']) < 0.01) {
                        $ambiguo = true;
                        break;
                    }
                }
                if ($ambiguo) {
                    $via = 'ambiguo'; // no se resuelve: se manda a revisión
                } else {
                    $trabajador = $mejor['trab'];
                    $via = 'nombre';
                }
            }

            $resultado[] = [
                'archivo' => $fila,
                'trabajador' => $trabajador,
                'via' => $via,
            ];
        }
        return $resultado;
    }

    private function tokens($s): array
    {
        $s = mb_strtoupper(trim((string) $s));
        $s = strtr($s, ['Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', 'Ü' => 'U']);
        $s = preg_replace('/[^A-Z0-9 ]/', ' ', $s);
        $parts = array_filter(explode(' ', preg_replace('/\s+/', ' ', trim($s))), function ($x) {
            return $x !== '' && !in_array($x, ['I', 'Y', 'DE', 'DEL', 'LA', 'EL', 'LOS', 'LAS']);
        });
        return array_values(array_unique($parts));
    }
}
