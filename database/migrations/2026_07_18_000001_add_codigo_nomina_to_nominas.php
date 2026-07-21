<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1) Columna + índice que cubra la FK trabajador_id (idempotente por si quedó parcial)
        Schema::table('nominas', function (Blueprint $table) {
            if (!Schema::hasColumn('nominas', 'codigo_nomina')) {
                // Código de la línea de nómina del gestoría (010072...). Distingue varias
                // líneas del mismo trabajador en el mismo mes (paga mensual, finiquito, contratos).
                $table->string('codigo_nomina', 50)->nullable()->after('importacion_id');
            }
            $table->index('trabajador_id', 'nominas_trabajador_id_index');
            $table->index(['anio', 'mes'], 'nominas_anio_mes_index');
        });

        // 2) Ahora sí se puede soltar la unique (la FK ya tiene índice propio)
        Schema::table('nominas', function (Blueprint $table) {
            $table->dropUnique('nominas_trabajador_id_anio_mes_unique');
        });
    }

    public function down(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            $table->unique(['trabajador_id', 'anio', 'mes'], 'nominas_trabajador_id_anio_mes_unique');
        });
        Schema::table('nominas', function (Blueprint $table) {
            $table->dropIndex('nominas_trabajador_id_index');
            $table->dropIndex('nominas_anio_mes_index');
            $table->dropColumn('codigo_nomina');
        });
    }
};
