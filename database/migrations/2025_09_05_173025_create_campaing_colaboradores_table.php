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
        Schema::create('campaing_colaboradores', function (Blueprint $table) {
            $table->unsignedBigInteger('idcampaign');
            $table->string('documento', 15)->collation('utf8mb4_spanish_ci');
            $table->string('nit', 10)->collation('utf8mb4_spanish_ci');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->string('sucursal', 100)
                ->default('ND')
                ->collation('utf8mb4_spanish_ci');

            $table->tinyInteger('email_notified')->default(0);

            // Definimos la PK compuesta
            $table->primary(['idcampaign', 'documento', 'nit']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaing_colaboradores');
    }
};
