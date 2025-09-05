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
        Schema::create('campaign_toys', function (Blueprint $table) {
            $table->id(); // int AUTO_INCREMENT PRIMARY KEY

            $table->char('combo', 3)
                ->default('NC')
                ->collation('utf8mb4_spanish_ci');

            $table->unsignedBigInteger('idcampaign');

            $table->string('referencia', 100)
                ->collation('utf8mb4_spanish_ci');

            $table->text('nombre')
                ->collation('utf8mb4_spanish_ci');

            $table->string('imagenppal', 100)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->string('genero', 10)
                ->default('UNISEX')
                ->collation('utf8mb4_spanish_ci');

            $table->string('desde', 3)
                ->default('0')
                ->collation('utf8mb4_spanish_ci');

            $table->string('hasta', 10)
                ->default('0')
                ->collation('utf8mb4_spanish_ci');

            $table->integer('unidades')->default(0);

            $table->integer('precio_unitario')->default(0);

            $table->string('porcentaje', 100)
                ->default('0')
                ->collation('utf8mb4_spanish_ci');

            $table->integer('seleccionadas')->default(0);

            $table->char('imgexists', 1)
                ->default('N')
                ->collation('utf8mb4_spanish_ci');

            $table->text('descripcion')
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->integer('escogidos')->default(0);

            $table->string('idoriginal', 15)
                ->default('0')
                ->collation('utf8mb4_spanish_ci');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_toys');
    }
};
