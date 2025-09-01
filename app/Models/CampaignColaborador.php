<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampaignColaborador extends Model
{
    protected $table = 'campaing_colaboradores'; // (sic) según tu DDL
    public $timestamps = true;
    public $incrementing = false;      // no hay id autoincremental
    protected $keyType = 'string';      // documento/nit son string
    protected $primaryKey = null;       // evitamos updates directos con save() sobre este modelo
    protected $guarded = [];            // o define fillable si prefieres

    // Sugerencia: usar este modelo para consultas simples (exists, where, etc.)
}
