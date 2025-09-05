<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('ciudades', function (Blueprint $table) {
            // PK natural (códigos DANE suelen ser de 5 chars)
            $table->char('codigo', 5)
                ->collation('utf8mb4_spanish_ci')
                ->primary();

            $table->string('nombre', 75)
                ->collation('utf8mb4_spanish_ci');

            $table->string('coddepto', 2)
                ->collation('utf8mb4_spanish_ci');

            $table->string('departamento', 75)
                ->collation('utf8mb4_spanish_ci');

            // timestamps con CURRENT_TIMESTAMP (MySQL 8+)
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Índices recomendados para consultas frecuentes
            $table->index('coddepto', 'ciudades_coddepto_idx');
            $table->index('nombre', 'ciudades_nombre_idx');
            $table->index('departamento', 'ciudades_departamento_idx');
            $table->index('created_at', 'ciudades_created_at_idx');

            // Índice compuesto útil para "todas las ciudades de un depto ordenadas por nombre"
            $table->index(['coddepto', 'nombre'], 'ciudades_depto_nombre_idx');
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::dropIfExists('ciudades');
    }
};
