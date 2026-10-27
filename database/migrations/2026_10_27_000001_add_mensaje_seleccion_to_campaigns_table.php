<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Título sobre los botones de hijos en la vista de selección.
            // Admite marcadores [COLABORADOR], [EMPRESA], [NOMBRE CAMPAÑA], [FECHAFIN].
            $table->string('mensaje_seleccion', 255)->nullable()->after('subject');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('mensaje_seleccion');
        });
    }
};
