<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaing_colaboradores', function (Blueprint $table) {
            // 1 = notificación habilitada (default), 0 = deshabilitada por el admin
            $table->tinyInteger('notify_enabled')->default(1)->after('email_notified');
        });
    }

    public function down(): void
    {
        Schema::table('campaing_colaboradores', function (Blueprint $table) {
            $table->dropColumn('notify_enabled');
        });
    }
};
