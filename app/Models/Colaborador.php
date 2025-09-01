<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Colaborador extends Model
{
    protected $table = 'colaboradores';

    protected $primaryKey = 'documento';
    public $incrementing = false;
    protected $keyType = 'string';

    public $timestamps = true;

    protected $fillable = [
        'documento',
        'nombre',
        'email',
        'direccion',
        'telefono',
        'ciudad',
        'observaciones',
        'barrio',
        'nit',
        'enviado',
        'politicadatos',
        'updatedatos',
        'sucursal',
        'welcome',
    ];

    protected $casts = [
        'enviado' => 'boolean', // smallint(0/1) -> bool
        // Nota: politicadatos/updatedatos/welcome son 'Y'/'N' (char(1)),
        // no se castea a boolean automáticamente.
    ];

    // Route Model Binding por 'documento'
    public function getRouteKeyName()
    {
        return 'documento';
    }

    // (Opcional) Relaciones:
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'nit', 'nit');
    }

    public function ciudadRef()
    {
        return $this->belongsTo(Ciudad::class, 'ciudad', 'codigo');
    }

    public function campaigns()
    {
        // belongsToMany(Campaign, tabla_pivot, fk_en_pivot_para_colaborador, fk_en_pivot_para_campaign, localKey_colaborador, localKey_campaign)
        return $this->belongsToMany(Campaign::class, 'campaing_colaboradores', 'documento', 'idcampaign', 'documento', 'id')
            ->withPivot(['nit', 'sucursal', 'email_notified'])
            ->withTimestamps();
    }
}
