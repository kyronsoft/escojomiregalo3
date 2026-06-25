<?php

namespace App\Exports;

use App\Models\CampaignToy;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class CampaignToysExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    public function __construct(protected int $campaignId) {}

    public function query()
    {
        return CampaignToy::query()
            ->where('idcampaign', $this->campaignId)
            ->orderBy('referencia');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Referencia',
            'Nombre',
            'Género',
            'Unidades',
            'Precio',
            'Porcentaje',
            'Descripción',
            'Imagen (URL)',
        ];
    }

    public function map($toy): array
    {
        return [
            $toy->id,
            $toy->referencia,
            $toy->nombre,
            $toy->genero,
            (int) ($toy->unidades ?? 0),
            $toy->precio_unitario,
            $toy->porcentaje,
            $toy->descripcion,
            $this->resolveFirstImageUrl($toy),
        ];
    }

    private function resolveFirstImageUrl(CampaignToy $toy): ?string
    {
        $raw = trim((string) ($toy->imagenppal ?? ''));
        if ($raw === '') {
            return null;
        }

        $first = collect(explode('+', $raw))
            ->map(fn($v) => trim($v))
            ->filter()
            ->first();

        if (!$first) {
            return null;
        }

        if (Str::startsWith($first, ['http://', 'https://'])) {
            return $first;
        }

        if (Str::startsWith($first, '/')) {
            $first = ltrim($first, '/');
        }

        $path = Str::startsWith($first, 'campaign_toys/')
            ? $first
            : "campaign_toys/{$toy->idcampaign}/{$first}";

        if (!Storage::disk('public')->exists($path)) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }
}
