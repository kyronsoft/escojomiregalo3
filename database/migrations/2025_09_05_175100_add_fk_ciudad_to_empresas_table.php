<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            // Índice dedicado para acelerar joins y filtros por ciudad
            $table->index('ciudad', 'empresas_ciudad_idx');

            // FK: empresas.ciudad -> ciudades.codigo
            // ON UPDATE CASCADE por si cambias el código de ciudad
            // ON DELETE SET NULL para no romper empresas si borras una ciudad
            $table->foreign('ciudad', 'empresas_ciudad_fk')
                ->references('codigo')->on('ciudades')
                ->onUpdate('cascade')
                ->onDelete('set null'); // requiere que empresas.ciudad sea nullable
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropForeign('empresas_ciudad_fk');
            $table->dropIndex('empresas_ciudad_idx');
        });
    }
};
