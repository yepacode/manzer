<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NominaImportacion extends Model
{
    protected $table = 'nomina_importaciones';

    protected $fillable = [
        'user_id',
        'anio',
        'mes',
        'archivo_nombre',
        'empresa_razon',
        'empresa_nif',
        'origen',
        'total_filas',
        'creadas',
        'omitidas',
        'con_error',
        'estado',
        'detalle',
    ];

    protected $casts = [
        'anio' => 'integer',
        'mes' => 'integer',
        'total_filas' => 'integer',
        'creadas' => 'integer',
        'omitidas' => 'integer',
        'con_error' => 'integer',
        'detalle' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function nominas(): HasMany
    {
        return $this->hasMany(Nomina::class, 'importacion_id');
    }

    public function getMesNombreAttribute(): string
    {
        return Nomina::MESES[$this->mes] ?? (string) $this->mes;
    }
}
