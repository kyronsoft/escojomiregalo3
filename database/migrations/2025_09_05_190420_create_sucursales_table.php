<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            // PK compuesta (consecutivo por empresa)
            $table->integer('consecutivo'); // NO auto-increment (es por empresa)
            $table->string('nit', 10)->collation('utf8mb4_spanish_ci'); // -> empresas.nit

            $table->string('nombre', 100)->nullable()->collation('utf8mb4_spanish_ci');
            $table->string('direccion', 100)->nullable()->collation('utf8mb4_spanish_ci');

            // -> ciudades.codigo (char(5))
            $table->char('idciudad', 5)->collation('utf8mb4_spanish_ci');

            $table->string('telefono', 15)->nullable()->collation('utf8mb4_spanish_ci');
            $table->string('nombrecontacto', 255)->nullable()->collation('utf8mb4_spanish_ci');

            // Timestamps con defaults como en tu DDL
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Primary Key compuesta
            $table->primary(['consecutivo', 'nit']);

            // === Relaciones (sin crear índices manuales) ===

            // FK: sucursales.nit -> empresas.nit
            $table->foreign('nit', 'sucursales_nit_fk')
                ->references('nit')->on('empresas')
                ->onDelete('cascade')   // al borrar empresa, borra sus sucursales
                ->onUpdate('cascade');

            // FK: sucursales.idciudad -> ciudades.codigo
            // La columna es NOT NULL, así que usamos RESTRICT en delete
            $table->foreign('idciudad', 'sucursales_idciudad_fk')
                ->references('codigo')->on('ciudades')
                ->onDelete('restrict')  // evita borrar una ciudad referenciada
                ->onUpdate('cascade');  // permite actualizar códigos (p.ej. normalización)
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropForeign('sucursales_nit_fk');
            $table->dropForeign('sucursales_idciudad_fk');
        });

        Schema::dropIfExists('sucursales');
    }
};
