<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Campaign extends Model
{
    protected $table = 'campaigns';

    protected $fillable = [
        'nit',
        'nombre',
        'idtipo',
        'fechaini',
        'fechafin',
        'banner',
        'demo',
        'doc_yeminus',
        'customlogin',
        'mailtext',
        'subject',
        'dashboard',
    ];

    protected $casts = [
        'fechaini'   => 'date',
        'fechafin'   => 'date',
        'idtipo'     => 'integer',
        'doc_yeminus' => 'integer',
        'dashboard'  => 'boolean',
        'nit' => 'string'
    ];

    public function setNitAttribute($value)
    {
        $this->attributes['nit'] = trim((string)$value);
    }

    // (Opcional) relación si tienes el modelo Empresa y FK nit
    public function empresa()
    {
        return $this->belongsTo(Empresa::class, 'nit', 'nit');
    }

    public function colaboradores()
    {
        return $this->belongsToMany(Colaborador::class, 'campaing_colaboradores', 'idcampaign', 'documento', 'id', 'documento')
            ->withPivot(['nit', 'sucursal', 'email_notified'])
            ->withTimestamps();
    }
}
