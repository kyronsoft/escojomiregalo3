<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Las campañas se ingresan en GMT-5 (America/Bogota): inicio 08:00:00 y fin 23:59:00 del día elegido.
 * Se guardan en UTC, así que el inicio queda a las 13:00:00 UTC del mismo día y el fin a las 04:59:00 UTC
 * del día siguiente. Esta migración reescribe las fechas existentes conservando el día que el usuario eligió
 * (la parte de fecha en UTC). Solo toca filas con las horas anteriores (00:00/08:00 de inicio y 23:59 de fin),
 * así que volver a ejecutarla no las desplaza otra vez.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("
            UPDATE campaigns
            SET fechaini = CONCAT(DATE(fechaini), ' 13:00:00'),
                fechafin = CONCAT(DATE_ADD(DATE(fechafin), INTERVAL 1 DAY), ' 04:59:00'),
                updated_at = updated_at
            WHERE TIME(fechaini) IN ('00:00:00', '08:00:00')
              AND TIME(fechafin) IN ('23:59:00', '23:59:59')
        ");
    }

    public function down(): void
    {
        DB::statement("
            UPDATE campaigns
            SET fechaini = CONCAT(DATE(CONVERT_TZ(fechaini, '+00:00', '-05:00')), ' 00:00:00'),
                fechafin = CONCAT(DATE(CONVERT_TZ(fechafin, '+00:00', '-05:00')), ' 23:59:59'),
                updated_at = updated_at
            WHERE TIME(fechaini) = '13:00:00'
              AND TIME(fechafin) = '04:59:00'
        ");
    }
};
