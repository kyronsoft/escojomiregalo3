<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Marca de finalización de la selección: al finalizar, el colaborador
            // no puede volver a ingresar (el login por documento no valida contraseña).
            $table->timestamp('finalizado_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('finalizado_at');
        });
    }
};
