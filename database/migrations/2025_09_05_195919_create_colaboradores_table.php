<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('colaboradores', function (Blueprint $table) {
            $table->string('documento', 15)
                ->collation('utf8mb4_spanish_ci')
                ->primary();

            $table->string('nombre', 100)->collation('utf8mb4_spanish_ci');
            $table->string('email', 75)->nullable()->collation('utf8mb4_spanish_ci');
            $table->string('direccion', 255)->nullable()->collation('utf8mb4_spanish_ci');
            $table->string('telefono', 10)->nullable()->collation('utf8mb4_spanish_ci');

            // Alineado con ciudades.codigo (char(5))
            $table->char('ciudad', 5)->nullable()->collation('utf8mb4_spanish_ci');

            $table->string('observaciones', 255)->nullable()->collation('utf8mb4_spanish_ci');
            $table->string('barrio', 100)->nullable()->collation('utf8mb4_spanish_ci');

            // → empresas.nit
            $table->string('nit', 10)->collation('utf8mb4_spanish_ci');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->smallInteger('enviado')->default(0);
            $table->char('politicadatos', 1)->default('N')->collation('utf8mb4_spanish_ci');
            $table->char('updatedatos', 1)->default('N')->collation('utf8mb4_spanish_ci');
            $table->string('sucursal', 100)->nullable()->collation('utf8mb4_spanish_ci');
            $table->char('welcome', 1)->default('N')->collation('utf8mb4_spanish_ci');
        });

        // === Relaciones (sin crear índices manuales; MySQL usará/reutilizará los necesarios) ===

        // colaboradores.nit → empresas.nit
        Schema::table('colaboradores', function (Blueprint $table) {
            $table->foreign('nit', 'colab_nit_fk')
                ->references('nit')->on('empresas')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // colaboradores.ciudad → ciudades.codigo (nullable)
            $table->foreign('ciudad', 'colab_ciudad_fk')
                ->references('codigo')->on('ciudades')
                ->onDelete('set null')
                ->onUpdate('cascade');
        });

        // campaing_colaboradores.documento → colaboradores.documento
        // (Esta tabla ya tiene índice por `documento`, no añadimos otro)
        Schema::table('campaing_colaboradores', function (Blueprint $table) {
            $table->foreign('documento', 'cc_documento_colaboradores_fk')
                ->references('documento')->on('colaboradores')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::table('campaing_colaboradores', function (Blueprint $table) {
            $table->dropForeign('cc_documento_colaboradores_fk');
        });

        Schema::table('colaboradores', function (Blueprint $table) {
            $table->dropForeign('colab_nit_fk');
            $table->dropForeign('colab_ciudad_fk');
        });

        Schema::dropIfExists('colaboradores');
    }
};
