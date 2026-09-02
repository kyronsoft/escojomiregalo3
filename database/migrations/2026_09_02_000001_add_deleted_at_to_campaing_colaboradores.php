<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaing_colaboradores', function (Blueprint $table) {
            // Tombstone: cuando el admin quita a un colaborador de la campaña
            // desde la pantalla, la fila NO se borra fisicamente, se marca aqui.
            // Asi una recarga posterior del Excel de importacion no la resucita.
            $table->timestamp('deleted_at')->nullable()->after('notify_enabled');
            $table->index('deleted_at', 'cc_deleted_at_idx');
        });
    }

    public function down(): void
    {
        Schema::table('campaing_colaboradores', function (Blueprint $table) {
            $table->dropIndex('cc_deleted_at_idx');
            $table->dropColumn('deleted_at');
        });
    }
};
