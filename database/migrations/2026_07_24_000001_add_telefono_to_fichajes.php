<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichajes', function (Blueprint $table) {
            // Teléfono del trabajador asociado a cada fichada (entrada / salida).
            // Nullable a nivel BD por los fichajes antiguos; la obligatoriedad se valida en la app.
            $table->string('telefono_entrada', 30)->nullable()->after('longitud_entrada');
            $table->string('telefono_salida', 30)->nullable()->after('longitud_salida');
        });
    }

    public function down(): void
    {
        Schema::table('fichajes', function (Blueprint $table) {
            $table->dropColumn(['telefono_entrada', 'telefono_salida']);
        });
    }
};
