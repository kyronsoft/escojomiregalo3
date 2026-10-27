<?php

namespace App\Models;

use App\Support\PublicStorageUrl;
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
        'color_boton_nino',
        'color_boton_nina',
        'color_boton_unisex',
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

    /**
     * URL del logo de la empresa, o null si no existe. Prueba primero la ruta
     * guardada en `logo` y, si no existe, las carpetas donde se han guardado
     * los logos (empresas/{nit} y campaigns/{nit}).
     */
    public function logoUrl(): ?string
    {
        $candidates = [$this->logo];
        foreach (['empresas', 'campaigns'] as $dir) {
            foreach (['png', 'jpg', 'jpeg'] as $ext) {
                $candidates[] = "{$dir}/{$this->nit}/logo.{$ext}";
            }
        }

        foreach (array_filter($candidates) as $candidate) {
            if ($url = PublicStorageUrl::ifExists($candidate)) {
                return $url;
            }
        }

        return null;
    }
}
