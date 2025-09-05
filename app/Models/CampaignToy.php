<?php

namespace App\Models;

use Illuminate\Support\Str;
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

    protected $appends = ['image_url', 'image_parts_count'];

    /**
     * URL a la primera imagen del juguete (o null si no hay).
     * Soporta:
     *  - "img.jpg" (nombre suelto) -> campaign_toys/{idcampaign}/img.jpg
     *  - "/campaign_toys/229/img.jpg" (con slash inicial)
     *  - "campaign_toys/229/img.jpg" (ruta relativa completa)
     *  - combos "a.jpg+b.jpg+..."
     */
    public function getImageUrlAttribute(): ?string
    {
        $raw = trim((string) ($this->imagenppal ?? ''));
        if ($raw === '') {
            return null;
        }

        $first = collect(explode('+', $raw))
            ->map(fn($v) => trim($v))
            ->filter()
            ->first();

        if (!$first) return null;

        // Normaliza a path relativo del disk('public')
        if (Str::startsWith($first, 'campaign_toys/')) {
            $path = $first; // ya es relativo correcto
        } elseif (Str::startsWith($first, '/')) {
            $path = ltrim($first, '/'); // quita /
        } else {
            $path = "campaign_toys/{$this->idcampaign}/{$first}";
        }

        return Storage::disk('public')->url($path);
    }

    /**
     * Cantidad de partes declaradas en imagenppal (para mostrar el badge +n).
     */
    public function getImagePartsCountAttribute(): int
    {
        $raw = trim((string) ($this->imagenppal ?? ''));
        if ($raw === '') return 0;

        return collect(explode('+', $raw))
            ->map(fn($v) => trim($v))
            ->filter()
            ->count();
    }

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
