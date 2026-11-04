<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Texto de la vista "¡Listo! Tu selección ha finalizado".
            // Admite marcadores [EMPRESA], [NOMBRE CAMPAÑA], [FECHAFIN].
            $table->text('mensaje_confirmacion')->nullable()->after('mensaje_seleccion');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('mensaje_confirmacion');
        });
    }
};
