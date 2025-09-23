<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Seleccionado extends Model
{
    use Auditable;
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
