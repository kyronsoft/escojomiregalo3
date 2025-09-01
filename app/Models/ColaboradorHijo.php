<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ColaboradorHijo extends Model
{
    protected $table = 'colaborador_hijos';

    protected $fillable = [
        'identificacion',
        'nombre_hijo',
        'genero',
        'rango_edad',
        'idcampaign',
    ];

    // Relaciones
    public function colaborador()
    {
        // colaboradores.documento (PK string)
        return $this->belongsTo(Colaborador::class, 'identificacion', 'documento');
    }

    public function campaign()
    {
        // campaigns.id (PK int)
        return $this->belongsTo(Campaign::class, 'idcampaign', 'id');
    }
}
