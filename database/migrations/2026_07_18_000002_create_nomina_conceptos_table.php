<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nomina_conceptos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('nomina_id')->constrained('nominas')->cascadeOnDelete();
            $table->string('codigo', 20)->nullable();          // código del concepto en el gestoría (1, 33, 999...)
            $table->string('concepto');                        // nombre del concepto (Salario Base, IRPF...)
            $table->decimal('importe', 12, 2)->default(0);
            $table->string('tipo', 20)->default('devengo');    // devengo | deduccion | base | total
            $table->unsignedSmallInteger('orden')->default(0); // para conservar el orden del recibo
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nomina_conceptos');
    }
};
