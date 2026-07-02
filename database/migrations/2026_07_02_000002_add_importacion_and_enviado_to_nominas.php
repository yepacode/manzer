<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            // De qué carga masiva salió la nómina (null = alta manual existente)
            $table->foreignId('importacion_id')->nullable()->after('documento_path')
                ->constrained('nomina_importaciones')->nullOnDelete();
            // Momento en que se envió el correo al trabajador (null = aún no enviada / no visible en su portal)
            $table->timestamp('enviado_at')->nullable()->after('importacion_id');
        });
    }

    public function down(): void
    {
        Schema::table('nominas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('importacion_id');
            $table->dropColumn('enviado_at');
        });
    }
};
