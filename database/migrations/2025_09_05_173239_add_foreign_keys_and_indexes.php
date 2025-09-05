<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /**
         * Tabla: campaigns
         * (No requiere índices extra ahora; la PK 'id' ya está.)
         */

        /**
         * Tabla: empresas
         * (PK 'nit' ya está; agregamos índice temporal si sueles filtrar por created_at.)
         */
        Schema::table('empresas', function (Blueprint $table) {
            $table->index('created_at', 'empresas_created_at_idx');
        });

        /**
         * Tabla: campaign_toys
         * - FK campaign_toys.idcampaign -> campaigns.id (CASCADE)
         * - Índices útiles por uso común:
         *   (idcampaign, referencia) para búsquedas y para soportar FK compuesta desde 'seleccionados'
         *   (idcampaign, genero) si filtras por género por campaña
         *   (idcampaign, idoriginal) si sueles mapear originales por campaña
         *   created_at para ordenamientos recientes
         */
        Schema::table('campaign_toys', function (Blueprint $table) {
            // Índices
            $table->index(['idcampaign', 'referencia'], 'ctoys_idc_ref_idx');
            $table->index(['idcampaign', 'genero'], 'ctoys_idc_genero_idx');
            $table->index(['idcampaign', 'idoriginal'], 'ctoys_idc_idorig_idx');
            $table->index('created_at', 'ctoys_created_at_idx');

            // FK a campaigns
            $table->foreign('idcampaign', 'ctoys_idcampaign_fk')
                ->references('id')->on('campaigns')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        /**
         * Tabla: campaing_colaboradores
         * - PK compuesta (idcampaign, documento, nit) ya existe
         * - FK campaing_colaboradores.idcampaign -> campaigns.id (CASCADE)
         * - FK campaing_colaboradores.nit -> empresas.nit (CASCADE)
         * - Índices adicionales para búsquedas por 'nit' o por 'documento' aislados
         *   (la PK ayuda por idcampaign, pero no optimiza consultas por nit/doc sin idcampaign)
         */
        Schema::table('campaing_colaboradores', function (Blueprint $table) {
            // Índices
            $table->index('nit', 'cc_nit_idx');
            $table->index('documento', 'cc_documento_idx');
            $table->index('created_at', 'cc_created_at_idx');

            // FKs
            $table->foreign('idcampaign', 'cc_idcampaign_fk')
                ->references('id')->on('campaigns')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            $table->foreign('nit', 'cc_nit_fk')
                ->references('nit')->on('empresas')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });

        /**
         * Tabla: seleccionados
         * - FK seleccionados.idcampaing -> campaigns.id (CASCADE)
         * - FK COMPUESTA (idcampaing, referencia) -> campaign_toys(idcampaign, referencia) (CASCADE)
         *   Para esto, arriba ya indexamos (idcampaign, referencia) en campaign_toys.
         * - Índices útiles:
         *   (idcampaing, documento) para buscar selecciones de un colaborador en una campaña
         *   (idcampaing, referencia) para empates rápidos a toys
         *   (idcampaing, selected) para filtros por estado
         *   documento e idhijo individuales para joins/búsquedas (tablas externas no incluidas)
         *   created_at para ordenamientos recientes
         */
        Schema::table('seleccionados', function (Blueprint $table) {
            // Índices
            $table->index(['idcampaing', 'documento'], 'sel_idc_doc_idx');
            $table->index(['idcampaing', 'referencia'], 'sel_idc_ref_idx');
            $table->index(['idcampaing', 'selected'], 'sel_idc_selected_idx');
            $table->index('documento', 'sel_documento_idx');
            $table->index('created_at', 'sel_created_at_idx');

            // FK directa a campaigns
            $table->foreign('idcampaing', 'sel_idcampaing_fk')
                ->references('id')->on('campaigns')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // FK compuesta a campaign_toys (idcampaign, referencia)
            $table->foreign(['idcampaing', 'referencia'], 'sel_idc_ref_fk')
                ->references(['idcampaign', 'referencia'])
                ->on('campaign_toys')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertimos en orden inverso

        Schema::table('seleccionados', function (Blueprint $table) {
            $table->dropForeign('sel_idcampaing_fk');
            $table->dropForeign('sel_idc_ref_fk');

            $table->dropIndex('sel_idc_doc_idx');
            $table->dropIndex('sel_idc_ref_idx');
            $table->dropIndex('sel_idc_selected_idx');
            $table->dropIndex('sel_documento_idx');
            $table->dropIndex('sel_idhijo_idx');
            $table->dropIndex('sel_created_at_idx');
        });

        Schema::table('campaing_colaboradores', function (Blueprint $table) {
            $table->dropForeign('cc_idcampaign_fk');
            $table->dropForeign('cc_nit_fk');

            $table->dropIndex('cc_nit_idx');
            $table->dropIndex('cc_documento_idx');
            $table->dropIndex('cc_created_at_idx');
        });

        Schema::table('campaign_toys', function (Blueprint $table) {
            $table->dropForeign('ctoys_idcampaign_fk');

            $table->dropIndex('ctoys_idc_ref_idx');
            $table->dropIndex('ctoys_idc_genero_idx');
            $table->dropIndex('ctoys_idc_idorig_idx');
            $table->dropIndex('ctoys_created_at_idx');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropIndex('empresas_created_at_idx');
        });
    }
};
