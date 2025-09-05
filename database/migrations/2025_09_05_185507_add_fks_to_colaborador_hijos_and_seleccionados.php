<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        /**
         * FKs de colaborador_hijos:
         *  - colaborador_hijos.idcampaing → campaigns.id
         *  - (colaborador_hijos.idcampaing, colaborador_hijos.identificacion)
         *        → (campaing_colaboradores.idcampaign, campaing_colaboradores.documento)
         *
         *   La PK de campaing_colaboradores es (idcampaign, documento, nit); el prefijo (idcampaign, documento)
         *   está indexado por la PK, por lo que sirve como índice referenciado.
         */
        Schema::table('colaborador_hijos', function (Blueprint $table) {
            // FK directa a campaigns
            $table->foreign('idcampaing', 'hijos_idcampaing_fk')
                ->references('id')->on('campaigns')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // FK compuesta a campaing_colaboradores (idcampaign, documento)
            $table->foreign(['idcampaing', 'identificacion'], 'hijos_idc_ident_fk')
                ->references(['idcampaign', 'documento'])
                ->on('campaing_colaboradores')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        /**
         * FK en seleccionados:
         *  - seleccionados.idhijo → colaborador_hijos.id
         *    (para asegurar que cada selección apunte a un hijo existente)
         */
        Schema::table('seleccionados', function (Blueprint $table) {
            // Índice (si no lo tenías ya)
            $table->index('idhijo', 'sel_idhijo_idx');

            // FK a colaborador_hijos
            $table->foreign('idhijo', 'sel_idhijo_fk')
                ->references('id')->on('colaborador_hijos')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::table('seleccionados', function (Blueprint $table) {
            $table->dropForeign('sel_idhijo_fk');
            // Si el índice lo añadiste en otra migración, comenta la línea de abajo
            // $table->dropIndex('sel_idhijo_idx');
        });

        Schema::table('colaborador_hijos', function (Blueprint $table) {
            $table->dropForeign('hijos_idcampaing_fk');
            $table->dropForeign('hijos_idc_ident_fk');
        });
    }
};
