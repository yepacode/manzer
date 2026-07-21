<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            // Nombre alternativo tal como viene en el archivo del gestoría, para emparejar
            // cuando no coincide con el nombre del sistema. Se rellena al mapear a mano (aprendizaje).
            $table->string('alias_nomina', 255)->nullable()->after('dni');
        });
    }

    public function down(): void
    {
        Schema::table('trabajadores', function (Blueprint $table) {
            $table->dropColumn('alias_nomina');
        });
    }
};
