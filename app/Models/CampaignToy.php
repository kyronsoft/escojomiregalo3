<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CampaignToy extends Model
{
    protected $table = 'campaign_toys';

    protected $fillable = [
        'combo',
        'idcampaign',
        'referencia',
        'nombre',
        'imagenppal',
        'genero',
        'desde',
        'hasta',
        'unidades',
        'precio_unitario',
        'porcentaje',
        'seleccionadas',
        'imgexists',
        'descripcion',
        'escogidos',
        'idoriginal',
    ];

    // Relación
    public function campaign()
    {
        return $this->belongsTo(Campaign::class, 'idcampaign', 'id');
    }

    // Helper para URL pública de la imagen
    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagenppal ? Storage::url($this->imagenppal) : null;
    }
}
