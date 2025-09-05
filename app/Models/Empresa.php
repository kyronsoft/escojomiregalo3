<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empresa extends Model
{
    protected $table = 'empresas';

    // Clave primaria string
    protected $primaryKey = 'nit';
    public $incrementing = false;
    protected $keyType = 'string';

    // Timestamps activos (coinciden con la tabla)
    public $timestamps = true;

    protected $fillable = [
        'nit',
        'nombre',
        'ciudad',
        'direccion',
        'logo',
        'banner',
        'imagen_login',
        'color_primario',
        'color_secundario',
        'color_terciario',
        'welcome_msg',
        'username',
        'codigoVendedor',
    ];

    protected $casts = ['nit' => 'string'];

    public function setNitAttribute($value)
    {
        $this->attributes['nit'] = trim((string)$value);
    }

    public function users()
    {
        return $this->hasMany(\App\Models\User::class, 'nit', 'nit');
    }
}
