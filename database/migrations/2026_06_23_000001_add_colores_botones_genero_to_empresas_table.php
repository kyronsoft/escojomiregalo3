<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('color_boton_nino', 7)->nullable()->after('color_terciario');
            $table->string('color_boton_nina', 7)->nullable()->after('color_boton_nino');
            $table->string('color_boton_unisex', 7)->nullable()->after('color_boton_nina');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn(['color_boton_nino', 'color_boton_nina', 'color_boton_unisex']);
        });
    }
};
