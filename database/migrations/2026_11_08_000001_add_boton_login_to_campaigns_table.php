<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            // Personalización del botón de login de la vista /custom-login.
            $table->string('boton_login_color', 7)->nullable()->after('customlogin');
            $table->string('boton_login_texto', 30)->nullable()->after('boton_login_color');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn(['boton_login_color', 'boton_login_texto']);
        });
    }
};
