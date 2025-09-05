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
        Schema::create('empresas', function (Blueprint $table) {
            $table->string('nit', 10)
                ->collation('utf8mb4_spanish_ci')
                ->primary();

            $table->string('nombre', 50)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->string('ciudad', 5)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->string('direccion', 100)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->string('logo', 255)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->string('banner', 255)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->string('imagen_login', 255)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->char('color_primario', 7)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->char('color_secundario', 7)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->char('color_terciario', 7)
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->text('welcome_msg')
                ->nullable()
                ->collation('utf8mb4_spanish_ci');

            $table->string('username', 255)
                ->collation('utf8mb4_spanish_ci');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->string('codigoVendedor', 10)
                ->default('00000')
                ->collation('utf8mb4_spanish_ci');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('empresas');
    }
};
