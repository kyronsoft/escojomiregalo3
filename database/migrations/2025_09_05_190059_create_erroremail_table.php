<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Run the migrations. */
    public function up(): void
    {
        Schema::create('erroremail', function (Blueprint $table) {
            $table->id(); // int AUTO_INCREMENT PK

            // Debe empatar con campaigns.id (bigint unsigned)
            $table->unsignedBigInteger('idcampaing');

            // Debe empatar con campaing_colaboradores.documento (varchar(15) utf8mb4_spanish_ci)
            $table->string('documento', 15)->collation('utf8mb4_spanish_ci');

            $table->string('email', 100)->collation('utf8mb4_spanish_ci');
            $table->text('status')->collation('utf8mb4_spanish_ci');

            // Timestamps según tu DDL (created_at SIN default, updated_at con CURRENT_TIMESTAMP)
            $table->timestamp('created_at'); // NOT NULL, sin default
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        // Relaciones (sin índices manuales para evitar duplicados)
        Schema::table('erroremail', function (Blueprint $table) {
            // FK directa a campaigns
            $table->foreign('idcampaing', 'errmail_idcampaing_fk')
                ->references('id')->on('campaigns')
                ->onDelete('cascade')
                ->onUpdate('cascade');

            // FK compuesta a campaing_colaboradores (idcampaign, documento)
            // Nota: en la tabla referenciada el campo es "idcampaign" (con 'g' + 'n')
            $table->foreign(['idcampaing', 'documento'], 'errmail_idc_doc_fk')
                ->references(['idcampaign', 'documento'])
                ->on('campaing_colaboradores')
                ->onDelete('cascade')
                ->onUpdate('cascade');
        });
    }

    /** Reverse the migrations. */
    public function down(): void
    {
        Schema::table('erroremail', function (Blueprint $table) {
            $table->dropForeign('errmail_idcampaing_fk');
            $table->dropForeign('errmail_idc_doc_fk');
        });

        Schema::dropIfExists('erroremail');
    }
};
