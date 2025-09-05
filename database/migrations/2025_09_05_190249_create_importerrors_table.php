<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('importerrors', function (Blueprint $table) {
            $table->id(); // int AUTO_INCREMENT PK

            // Número de fila del archivo importado
            $table->integer('row')->nullable();

            // Campos de detalle del error (normalizados a utf8mb4_spanish_ci)
            $table->string('attribute', 100)->nullable()->collation('utf8mb4_spanish_ci');
            $table->string('errors', 255)->nullable()->collation('utf8mb4_spanish_ci');
            $table->string('values', 255)->nullable()->collation('utf8mb4_spanish_ci');

            // Solo created_at (como en tu DDL), con default CURRENT_TIMESTAMP
            $table->timestamp('created_at')->nullable()->useCurrent();

            // Índices útiles (no hay FKs ni índices previos en esta tabla, así que no hay riesgo de duplicados)
            $table->index('created_at', 'importerrors_created_at_idx');
            $table->index('row', 'importerrors_row_idx');
            $table->index('attribute', 'importerrors_attribute_idx');
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::table('importerrors', function (Blueprint $table) {
            $table->dropIndex('importerrors_created_at_idx');
            $table->dropIndex('importerrors_row_idx');
            $table->dropIndex('importerrors_attribute_idx');
        });

        Schema::dropIfExists('importerrors');
    }
};
