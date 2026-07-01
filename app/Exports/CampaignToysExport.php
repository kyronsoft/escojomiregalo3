<?php

namespace App\Exports;

use App\Models\CampaignToy;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithDrawings;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class CampaignToysExport implements FromQuery, WithHeadings, WithMapping, WithDrawings, WithEvents, WithColumnWidths, ShouldAutoSize
{
    use Exportable;

    /** @var array<int, string|null> */
    private array $imagePathCache = [];

    /** @var array<int, string> */
    private array $temporaryFiles = [];

    public function __construct(protected int $campaignId) {}

    public function __destruct()
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_string($file) && $file !== '' && is_file($file)) {
                @unlink($file);
            }
        }
    }

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
            'Imagen',
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
            null,
            $this->resolveFirstImageUrl($toy),
        ];
    }

    public function drawings(): array
    {
        $drawings = [];
        $row = 2;

        foreach ($this->query()->get() as $toy) {
            $path = $this->resolveFirstImagePath($toy);

            if ($path && $this->isEmbeddableImage($path)) {
                $drawing = new Drawing();
                $drawing->setName('Foto ' . $toy->referencia);
                $drawing->setDescription((string) $toy->nombre);
                $drawing->setPath($path);
                $drawing->setHeight(60);
                $drawing->setCoordinates('I' . $row);
                $drawing->setOffsetX(8);
                $drawing->setOffsetY(6);
                $drawings[] = $drawing;
            }

            $row++;
        }

        return $drawings;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $event->sheet->getDelegate()->getDefaultRowDimension()->setRowHeight(54);
                $event->sheet->getDelegate()->getRowDimension(1)->setRowHeight(22);

                $row = 2;
                foreach ($this->query()->get() as $toy) {
                    $event->sheet->getDelegate()->getRowDimension($row)->setRowHeight(54);
                    $row++;
                }
            },
        ];
    }

    public function columnWidths(): array
    {
        return [
            'I' => 16,
            'J' => 42,
        ];
    }

    private function resolveFirstImageUrl(CampaignToy $toy): ?string
    {
        $first = $this->firstImageValue($toy);
        if (!$first) {
            return null;
        }

        if (Str::startsWith($first, ['http://', 'https://'])) {
            return $first;
        }

        $path = $this->resolveFirstImageStoragePath($toy);
        if (!$path) {
            return null;
        }

        return Storage::disk('public')->url($path);
    }

    private function resolveFirstImagePath(CampaignToy $toy): ?string
    {
        if (array_key_exists($toy->id, $this->imagePathCache)) {
            return $this->imagePathCache[$toy->id];
        }

        $first = $this->firstImageValue($toy);
        if (!$first) {
            return $this->imagePathCache[$toy->id] = null;
        }

        if (Str::startsWith($first, ['http://', 'https://'])) {
            return $this->imagePathCache[$toy->id] = $this->downloadRemoteImage($first);
        }

        $path = $this->resolveFirstImageStoragePath($toy);
        if (!$path) {
            return $this->imagePathCache[$toy->id] = null;
        }

        return $this->imagePathCache[$toy->id] = Storage::disk('public')->path($path);
    }

    private function isEmbeddableImage(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $info = @getimagesize($path);
        if (!$info) {
            return false;
        }

        return in_array($info[2] ?? null, [\IMAGETYPE_JPEG, \IMAGETYPE_PNG, \IMAGETYPE_GIF], true);
    }

    private function resolveFirstImageStoragePath(CampaignToy $toy): ?string
    {
        $first = $this->firstImageValue($toy);
        if (!$first || Str::startsWith($first, ['http://', 'https://'])) {
            return null;
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

        return $path;
    }

    private function downloadRemoteImage(string $url): ?string
    {
        try {
            $response = Http::timeout(15)->get($url);
            if (!$response->successful()) {
                return null;
            }

            $body = $response->body();
            if ($body === '') {
                return null;
            }

            $extension = $this->guessExtensionFromContentType($response->header('Content-Type') ?? '');
            $tmp = tempnam(sys_get_temp_dir(), 'campaign_toy_');
            if ($tmp === false) {
                return null;
            }

            $path = $tmp . $extension;
            rename($tmp, $path);
            file_put_contents($path, $body);
            $this->temporaryFiles[] = $path;

            return $path;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function guessExtensionFromContentType(string $contentType): string
    {
        $mime = strtolower(trim(explode(';', $contentType)[0] ?? ''));

        return match ($mime) {
            'image/jpeg', 'image/jpg' => '.jpg',
            'image/png'               => '.png',
            'image/gif'               => '.gif',
            'image/webp'              => '.webp',
            'image/bmp'               => '.bmp',
            default                   => '.img',
        };
    }

    private function firstImageValue(CampaignToy $toy): ?string
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

        return $first;
    }
}
