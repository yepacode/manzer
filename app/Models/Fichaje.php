<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fichaje extends Model
{
    protected $table = 'fichajes';

    protected $fillable = [
        'trabajador_id',
        'obra_id',
        'fecha',
        'hora_entrada',
        'latitud_entrada',
        'longitud_entrada',
        'telefono_entrada',
        'precision_entrada',
        'ip_entrada',
        'dispositivo_entrada',
        'hora_salida',
        'latitud_salida',
        'longitud_salida',
        'telefono_salida',
        'precision_salida',
        'ip_salida',
        'dispositivo_salida',
        'horas_trabajadas',
        'horas_extra',
        'validado',
        'validado_por',
        'fecha_validacion',
        'corregido',
        'corregido_por',
        'motivo_correccion',
        'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_entrada' => 'datetime:H:i',
        'hora_salida' => 'datetime:H:i',
        'fecha_validacion' => 'datetime',
        'horas_trabajadas' => 'decimal:2',
        'horas_extra' => 'decimal:2',
        'latitud_entrada' => 'decimal:8',
        'longitud_entrada' => 'decimal:8',
        'latitud_salida' => 'decimal:8',
        'longitud_salida' => 'decimal:8',
        'validado' => 'boolean',
        'corregido' => 'boolean',
    ];

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class);
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    public function validadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'validado_por');
    }

    public function corregidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'corregido_por');
    }

    // Scopes
    public function scopePendientesValidar($query)
    {
        return $query->where('validado', false);
    }

    public function scopeDelMes($query, $mes = null, $anio = null)
    {
        $mes = $mes ?? now()->month;
        $anio = $anio ?? now()->year;

        return $query->whereMonth('fecha', $mes)->whereYear('fecha', $anio);
    }
}
