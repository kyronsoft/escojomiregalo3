<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('parametros', function (Blueprint $table) {
            $table->id(); // int AUTO_INCREMENT PK

            $table->string('nombre', 100)
                ->collation('utf8mb4_spanish_ci');

            $table->text('valor')
                ->collation('utf8mb4_spanish_ci');

            // created_at NULL DEFAULT CURRENT_TIMESTAMP
            $table->timestamp('created_at')->nullable()->useCurrent();

            // updated_at NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Índices recomendados
            // Un parámetro suele buscarse por nombre; si quieres evitar duplicados, usa UNIQUE:
            $table->unique('nombre', 'parametros_nombre_uq');

            // Útil para ordenar/filtrar por fecha
            $table->index('created_at', 'parametros_created_at_idx');
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::table('parametros', function (Blueprint $table) {
            $table->dropUnique('parametros_nombre_uq');
            $table->dropIndex('parametros_created_at_idx');
        });

        Schema::dropIfExists('parametros');
    }
};
