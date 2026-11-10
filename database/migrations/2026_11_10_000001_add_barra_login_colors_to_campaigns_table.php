<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Barra inferior de ingreso de la vista /custom-login (independiente del botón del modal).
            $table->string('barra_login_color', 7)->nullable()->after('boton_login_texto');
            $table->string('barra_login_texto_color', 7)->nullable()->after('barra_login_color');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['barra_login_color', 'barra_login_texto_color']);
        });
    }
};
