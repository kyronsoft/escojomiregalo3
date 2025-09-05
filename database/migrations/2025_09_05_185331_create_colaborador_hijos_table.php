<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('colaborador_hijos', function (Blueprint $table) {
            $table->id(); // PK autoincrement

            $table->string('identificacion', 15)
                ->collation('utf8mb4_spanish_ci'); // debe coincidir con campaing_colaboradores.documento

            $table->string('nombre_hijo', 100)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->string('genero', 10)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->string('rango_edad', 15)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            // Debe empatar con campaigns.id (bigint unsigned)
            $table->unsignedBigInteger('idcampaing');

            // timestamps con CURRENT_TIMESTAMP + on update
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            // Índices base útiles
            $table->index(['idcampaing', 'identificacion'], 'hijos_idc_ident_idx');
            $table->index(['idcampaing', 'genero'], 'hijos_idc_genero_idx');
            $table->index(['idcampaing', 'rango_edad'], 'hijos_idc_rango_idx');
            $table->index('identificacion', 'hijos_ident_idx');
            $table->index('created_at', 'hijos_created_at_idx');
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::dropIfExists('colaborador_hijos');
    }
};
