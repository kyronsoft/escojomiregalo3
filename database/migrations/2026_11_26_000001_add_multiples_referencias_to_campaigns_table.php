<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Permite que los colaboradores elijan más de una referencia por hijo.
     * Por defecto está desactivado (1 referencia por hijo), así las campañas
     * existentes conservan su comportamiento actual.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->boolean('multiples_referencias')->default(false)->after('mostrargenero');
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropColumn('multiples_referencias');
        });
    }
};
