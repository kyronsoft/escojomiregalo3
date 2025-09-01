<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seleccionado extends Model
{
    protected $table = 'seleccionados';

    protected $fillable = [
        'documento',     // documento del colaborador
        'idcampaing',    // ¡así está en la tabla!
        'idhijo',
        'referencia',
        'selected',      // 'Y' / 'N'
    ];

    // Tiene created_at / updated_at -> timestamps ON por defecto
}
