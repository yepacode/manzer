<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nomina_importaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // quién cargó
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('mes');
            $table->string('archivo_nombre');                 // nombre original del Excel subido
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('creadas')->default(0);   // nóminas creadas
            $table->unsignedInteger('omitidas')->default(0);  // filas saltadas (duplicadas, etc.)
            $table->unsignedInteger('con_error')->default(0); // filas con error de validación
            $table->string('estado')->default('procesada');   // procesada | con_errores | fallida
            $table->json('detalle')->nullable();              // [{fila, dni, motivo}] de omitidas/errores
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_importaciones');
    }
};
