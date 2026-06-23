<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (!Schema::hasColumn('empresas', 'color_boton_nino')) {
                $table->string('color_boton_nino', 10)->default('#BA895D')->after('color_terciario');
            }
            if (!Schema::hasColumn('empresas', 'color_boton_nina')) {
                $table->string('color_boton_nina', 10)->default('#1B4C43')->after('color_boton_nino');
            }
            if (!Schema::hasColumn('empresas', 'color_boton_unisex')) {
                $table->string('color_boton_unisex', 10)->default('#000000')->after('color_boton_nina');
            }
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $cols = array_filter(
                ['color_boton_nino', 'color_boton_nina', 'color_boton_unisex'],
                fn($c) => Schema::hasColumn('empresas', $c)
            );
            if ($cols) {
                $table->dropColumn(array_values($cols));
            }
        });
    }
};
