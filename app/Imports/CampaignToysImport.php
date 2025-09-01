<?php

namespace App\Imports;

use App\Models\Campaign;
use App\Models\CampaignToy;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Row;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Validators\Failure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class CampaignToysImport implements OnEachRow, WithHeadingRow, SkipsOnFailure, SkipsEmptyRows, WithBatchInserts, WithChunkReading
{
    use Importable, SkipsFailures;

    protected Campaign $campaign;
    protected int $created = 0;
    protected int $updated = 0;
    protected int $skipped = 0;

    public function __construct(Campaign $campaign)
    {
        $this->campaign = $campaign;
    }

    public function onRow(Row $row)
    {
        $r = collect($row->toArray());

        $codigo      = trim((string) ($r['codigo'] ?? ''));
        $nombre      = trim((string) ($r['nombre'] ?? ''));
        $descripcion = trim((string) ($r['descripcion'] ?? ''));

        if ($codigo === '' || $nombre === '') {
            $this->skipped++;
            return;
        }

        // Normalizar género
        $genero = strtoupper(trim((string) ($r['genero'] ?? '')));
        if ($genero === '') {
            $genero = 'UNISEX';
        } else {
            $g = Str::lower($genero);
            if (Str::contains($g, 'niña')) {
                $genero = 'F';
            } elseif (Str::contains($g, 'niño')) {
                $genero = 'M';
            } elseif (in_array($g, ['m', 'f', 'unisex'])) {
                $genero = strtoupper($g);
            } else {
                $genero = 'UNISEX';
            }
        }

        // Campos numéricos seguros
        $desde      = (string) ($r['desde'] ?? '0');
        $hasta      = (string) ($r['hasta'] ?? '0');
        $unidades   = (int) ($r['unidades'] ?? 0);
        $porcentaje = (string) ($r['porcentaje'] ?? '0');

        // Detectar combo (una sola fila por combo)
        $isCombo = Str::contains($codigo, '+');

        // ---- NUEVO: construir imagenppal asegurando .jpg ----
        $imagenppal = $this->buildImagenPpalFromCodigo($codigo);

        $payload = [
            'combo'           => $isCombo ? 'COM' : 'NC',
            'idcampaign'      => $this->campaign->id,
            'referencia'      => $codigo,     // código completo (con + si es combo)
            'nombre'          => $nombre,
            'imagenppal'      => $imagenppal, // <- asegurado a .jpg (partes separadas por + si aplica)
            'genero'          => $genero,
            'desde'           => $desde,
            'hasta'           => $hasta,
            'unidades'        => $unidades,
            'precio_unitario' => 0,
            'porcentaje'      => $porcentaje,
            'seleccionadas'   => 0,
            'imgexists'       => 'N',
            'descripcion'     => $descripcion,
            'escogidos'       => 0
        ];

        // Upsert por (idcampaign + referencia)
        $existing = CampaignToy::where('idcampaign', $this->campaign->id)
            ->where('referencia', $codigo)
            ->first();

        if ($existing) {
            $existing->update($payload);
            $this->updated++;
        } else {
            CampaignToy::create($payload);
            $this->created++;
        }
    }

    /**
     * Asegura que cada código (o parte del combo) termine en .jpg
     * - Si tiene extensión distinta, la reemplaza por .jpg
     * - Si no tiene extensión, la agrega
     * - Para combos, devuelve "parte1.jpg+parte2.jpg+...".
     */
    protected function buildImagenPpalFromCodigo(string $codigo): string
    {
        $codigo = trim($codigo);
        if ($codigo === '') return '';

        // ¿Es combo?
        if (Str::contains($codigo, '+')) {
            $parts = array_filter(array_map('trim', explode('+', $codigo)));
            // máximo 6 según tu regla
            $parts = array_slice($parts, 0, 6);
            $parts = array_map(function ($p) {
                return $this->ensureJpg($p);
            }, $parts);

            return implode('+', $parts);
        }

        // No combo
        return $this->ensureJpg($codigo);
    }

    /**
     * Asegura que una cadena termine en .jpg (case-insensitive).
     * - Si ya termina en .jpg, la deja tal cual.
     * - Si tiene otra extensión, la reemplaza por .jpg.
     * - Si no tiene extensión, le agrega .jpg.
     */
    protected function ensureJpg(string $name): string
    {
        $name = trim($name);
        if ($name === '') return $name;

        $lower = Str::lower($name);
        if (Str::endsWith($lower, '.jpg')) {
            return $name; // ya es .jpg
        }

        // Si tiene "cualquier" extensión, reemplazar por .jpg
        if (Str::contains($name, '.')) {
            $base = substr($name, 0, strrpos($name, '.'));
            return $base . '.jpg';
        }

        // No tiene extensión, agregar .jpg
        return $name . '.jpg';
    }

    public function batchSize(): int
    {
        return 500;
    }
    public function chunkSize(): int
    {
        return 500;
    }

    public function summary(): array
    {
        return [
            'creados'     => $this->created,
            'actualizados' => $this->updated,
            'omitidos'    => $this->skipped,
        ];
    }
}
