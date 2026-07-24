<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fichajes', function (Blueprint $table) {
            // Precisión del GPS en metros (fiabilidad de la ubicación)
            $table->decimal('precision_entrada', 8, 2)->nullable()->after('telefono_entrada');
            $table->string('ip_entrada', 45)->nullable()->after('precision_entrada');
            $table->string('dispositivo_entrada', 255)->nullable()->after('ip_entrada');

            $table->decimal('precision_salida', 8, 2)->nullable()->after('telefono_salida');
            $table->string('ip_salida', 45)->nullable()->after('precision_salida');
            $table->string('dispositivo_salida', 255)->nullable()->after('ip_salida');
        });
    }

    public function down(): void
    {
        Schema::table('fichajes', function (Blueprint $table) {
            $table->dropColumn([
                'precision_entrada', 'ip_entrada', 'dispositivo_entrada',
                'precision_salida', 'ip_salida', 'dispositivo_salida',
            ]);
        });
    }
};
