<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NominaConcepto extends Model
{
    protected $table = 'nomina_conceptos';

    public $timestamps = false;

    protected $fillable = [
        'nomina_id',
        'codigo',
        'concepto',
        'importe',
        'tipo',
        'orden',
    ];

    protected $casts = [
        'importe' => 'decimal:2',
        'orden' => 'integer',
    ];

    public const TIPO_DEVENGO = 'devengo';
    public const TIPO_DEDUCCION = 'deduccion';
    public const TIPO_BASE = 'base';
    public const TIPO_TOTAL = 'total';

    public function nomina(): BelongsTo
    {
        return $this->belongsTo(Nomina::class);
    }
}
