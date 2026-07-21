<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Nomina extends Model
{
    protected $table = 'nominas';

    protected $fillable = [
        'trabajador_id',
        'anio',
        'mes',
        'salario_bruto',
        'ss_empresa',
        'ss_trabajador',
        'irpf',
        'liquido',
        'documento_path',
        'notas',
        'importacion_id',
        'enviado_at',
        'codigo_nomina',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'salario_bruto' => 'decimal:2',
        'ss_empresa' => 'decimal:2',
        'ss_trabajador' => 'decimal:2',
        'irpf' => 'decimal:2',
        'liquido' => 'decimal:2',
        'enviado_at' => 'datetime',
    ];

    public const MESES = [
        1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
        5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
        9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
    ];

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function importacion(): BelongsTo
    {
        return $this->belongsTo(NominaImportacion::class, 'importacion_id');
    }

    public function conceptos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NominaConcepto::class)->orderBy('orden');
    }

    public function devengos(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->conceptos()->where('tipo', NominaConcepto::TIPO_DEVENGO);
    }

    public function deducciones(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->conceptos()->where('tipo', NominaConcepto::TIPO_DEDUCCION);
    }

    public function bases(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->conceptos()->where('tipo', NominaConcepto::TIPO_BASE);
    }

    /**
     * Coste total para la empresa = salario bruto + SS a cargo de la empresa.
     */
    public function getCosteEmpresaAttribute(): float
    {
        return (float) $this->salario_bruto + (float) $this->ss_empresa;
    }

    public function getMesNombreAttribute(): string
    {
        return self::MESES[$this->mes] ?? (string) $this->mes;
    }

    public function scopeDelPeriodo($query, int $anio, ?int $mes = null)
    {
        $query->where('anio', $anio);
        if ($mes) {
            $query->where('mes', $mes);
        }
        return $query;
    }
}
