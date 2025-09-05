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
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id(); // int AUTO_INCREMENT PRIMARY KEY
            $table->string('nit', 10)->collation('utf8mb4_spanish_ci');
            $table->string('nombre', 100)->collation('utf8mb4_spanish_ci');
            $table->tinyInteger('idtipo');
            $table->dateTime('fechaini');
            $table->dateTime('fechafin');
            $table->string('banner', 100)
                ->collation('utf8mb4_spanish_ci')
                ->default('ND');
            $table->char('demo', 3)
                ->collation('utf8mb4_spanish_ci')
                ->default('off');
            $table->integer('doc_yeminus')->default(0);

            // timestamps con valores por defecto
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')
                ->useCurrent()
                ->useCurrentOnUpdate();

            $table->text('customlogin')->nullable()->collation('utf8mb4_spanish_ci');
            $table->text('mailtext')->collation('utf8mb4_spanish_ci');
            $table->string('subject', 150)->collation('utf8mb4_spanish_ci');
            $table->tinyInteger('dashboard')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
