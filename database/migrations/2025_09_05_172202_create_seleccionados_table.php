<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('seleccionados', function (Blueprint $table) {
            $table->id(); // equivale a int AUTO_INCREMENT PRIMARY KEY
            $table->string('documento', 15)->collation('utf8mb4_spanish_ci');
            $table->unsignedBigInteger('idcampaing');
            $table->unsignedBigInteger('idhijo');
            $table->string('referencia', 100)->collation('utf8mb4_spanish_ci');
            $table->char('selected', 1)->default('N')->collation('utf8mb4_spanish_ci');
            $table->timestamps(); // crea created_at y updated_at con CURRENT_TIMESTAMP
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seleccionados');
    }
};
