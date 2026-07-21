<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nomina_importaciones', function (Blueprint $table) {
            // Empresa leída del archivo del gestoría (para el recibo oficial)
            $table->string('empresa_razon')->nullable()->after('archivo_nombre');
            $table->string('empresa_nif', 30)->nullable()->after('empresa_razon');
            $table->string('origen', 20)->default('plantilla')->after('empresa_nif'); // plantilla | gestoria
        });
    }

    public function down(): void
    {
        Schema::table('nomina_importaciones', function (Blueprint $table) {
            $table->dropColumn(['empresa_razon', 'empresa_nif', 'origen']);
        });
    }
};
