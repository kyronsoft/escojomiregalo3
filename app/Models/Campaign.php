<?php

namespace App\Models;

use App\Support\PublicStorageUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Campaign extends Model
{
    /** Zona en la que el usuario ingresa, muestra y vive las fechas (GMT-5). Las fechas se guardan en UTC. */
    public const ZONA_LOCAL = 'America/Bogota';

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
        'mensaje_seleccion',
        'dashboard',
        'mostrargenero',
    ];

    protected $casts = [
        'fechaini'   => 'datetime',
        'fechafin'   => 'datetime',
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

    /**
     * URL pública del banner, o null si no está configurado o el archivo
     * no existe en el disco público. Las vistas (producto, carrito, checkout)
     * y el correo usan este método para no referenciar imágenes inexistentes.
     */
    public function bannerUrl(): ?string
    {
        return PublicStorageUrl::ifExists($this->banner);
    }

    /** Fecha de inicio en GMT-5 (null si no tiene). */
    public function fechainiLocal(): ?Carbon
    {
        return $this->fechaini?->copy()->setTimezone(self::ZONA_LOCAL);
    }

    /** Fecha de fin en GMT-5 (null si no tiene). */
    public function fechafinLocal(): ?Carbon
    {
        return $this->fechafin?->copy()->setTimezone(self::ZONA_LOCAL);
    }
}
