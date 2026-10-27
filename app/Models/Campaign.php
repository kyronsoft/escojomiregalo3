<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

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
        'mostrargenero',
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

    /**
     * URL pública del banner, o null si no está configurado o el archivo
     * no existe en el disco público. Las vistas (producto, carrito, checkout)
     * y el correo usan este método para no referenciar imágenes inexistentes.
     */
    public function bannerUrl(): ?string
    {
        $path = trim((string) $this->banner);
        if ($path === '' || $path === 'ND') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('#^(storage/app/public/|app/public/|public/|storage/)#i', '', $path);

        return Storage::disk('public')->exists($path)
            ? url(Storage::disk('public')->url($path))
            : null;
    }
}
