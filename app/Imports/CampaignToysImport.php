<?php

namespace App\Imports;

use App\Models\Campaign;
use App\Models\CampaignToy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

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

    /** ============== Helpers ============== */

    private function rowIndex(Row $row): int
    {
        // Con WithHeadingRow, getIndex() ya devuelve el número real en Excel
        return (int)$row->getIndex();
    }

    /** Busca el primer valor no vacío entre alias de encabezados (normaliza como string) */
    private function firstString(array $arr, array $aliases): string
    {
        foreach ($aliases as $k) {
            if (!array_key_exists($k, $arr)) continue;
            $v = $arr[$k];
            if ($v === null) continue;
            $s = is_string($v) ? trim($v) : trim((string)$v);
            if ($s !== '') return $s;
        }
        return '';
    }

    /** Convierte a int seguro; si falla, devuelve null */
    private function toIntOrNull($v): ?int
    {
        if ($v === null || $v === '') return null;
        if (is_int($v)) return $v;
        if (is_float($v)) return (int)round($v);
        $s = is_string($v) ? trim($v) : (string)$v;
        if ($s === '') return null;
        if (!preg_match('/^-?\d+$/', $s)) return null;
        return (int)$s;
    }

    /** Registra error en importerrors (máx 255 chars en `values`) */
    private function logError(int $row, string $attribute, string $message, array $values = []): void
    {
        DB::table('importerrors')->insert([
            'row'        => $row,
            'attribute'  => Str::limit($attribute, 100, ''),
            'errors'     => Str::limit($message, 255, ''),
            'values'     => Str::limit(json_encode($values, JSON_UNESCAPED_UNICODE), 255, ''),
            'created_at' => now(),
        ]);
    }

    /** Normaliza género a F/M/UNISEX, registrando si vino algo inesperado */
    private function normalizeGenero(string $generoRaw, int $excelRow, string $codigo): string
    {
        $g = Str::lower(trim($generoRaw));
        if ($g === '') return 'UNISEX';

        if (Str::contains($g, 'niña')) return 'F';
        if (Str::contains($g, 'nino') || Str::contains($g, 'niño')) return 'M';

        if (in_array($g, ['f', 'm', 'unisex'], true)) {
            return strtoupper($g);
        }

        // Desconocido → UNISEX y lo registramos
        $this->logError($excelRow, 'genero', 'Valor de género no reconocido, se usó UNISEX.', [
            'codigo'  => $codigo,
            'genero'  => $generoRaw,
        ]);
        return 'UNISEX';
    }

    /** Asegura ".jpg" por parte; combos con '+' son manejados afuera */
    private function ensureJpg(string $name): string
    {
        $name = trim($name);
        if ($name === '') return $name;

        $lower = Str::lower($name);
        if (Str::endsWith($lower, '.jpg')) return $name;

        // Si tiene extensión distinta, reemplazar
        if (Str::contains($name, '.')) {
            $base = substr($name, 0, strrpos($name, '.'));
            return $base . '.jpg';
        }
        return $name . '.jpg';
    }

    /** Construye imagenppal asegurando .jpg; retorna cadena y arreglo de partes */
    private function buildImagenPpalFromCodigo(string $codigo): array
    {
        $codigo = trim($codigo);
        if ($codigo === '') return ['', []];

        if (Str::contains($codigo, '+')) {
            $parts = array_filter(array_map('trim', explode('+', $codigo)));
            $parts = array_slice($parts, 0, 6);
            $parts = array_map(fn($p) => $this->ensureJpg($p), $parts);
            return [implode('+', $parts), $parts];
        }

        $file = $this->ensureJpg($codigo);
        return [$file, [$file]];
    }

    /** Path relativo dentro de disk('public') para una imagen */
    private function imageStoragePath(string $file): string
    {
        $file = ltrim($file, '/');
        if (Str::startsWith($file, 'campaign_toys/')) {
            return $file;
        }
        return "campaign_toys/{$this->campaign->id}/{$file}";
    }

    /** Verifica existencia de TODAS las imágenes; devuelve [allExist, missing[], checked[]] */
    private function checkImages(array $files): array
    {
        $checked = [];
        $missing = [];

        foreach ($files as $f) {
            $path = $this->imageStoragePath($f);
            $checked[] = $path;
            if (!Storage::disk('public')->exists($path)) {
                $missing[] = $path;
            }
        }
        return [count($missing) === 0, $missing, $checked];
    }

    /** ============== Import logic ============== */

    public function onRow(Row $row)
    {
        $excelRow = $this->rowIndex($row);

        try {
            // Normalizamos headers: Laravel Excel mantiene las llaves tal como el encabezado (saneadas).
            // Para soportar distintos archivos, buscamos por alias.
            $raw = $row->toArray();
            // Bajamos todas las llaves a snake/trim para facilitar (sin perder valores originales)
            $R = [];
            foreach ($raw as $k => $v) {
                $key = Str::of($k)->lower()->replace(' ', '_')->replace('__', '_')->value();
                $R[$key] = $v;
            }

            // Aliases comunes (e.g. archivo "Referencias Agrosavia")
            $codigo      = $this->firstString($R, ['codigo', 'referencia', 'cod_ref', 'cod_referencia', 'sku', 'codigo_referencia']);
            $nombre      = $this->firstString($R, ['nombre', 'nombre_producto', 'producto', 'descripcion_corta', 'titulo']);
            $descripcion = $this->firstString($R, ['descripcion', 'descripcion_larga', 'detalle', 'caracteristicas', 'observaciones']);

            if ($codigo === '' || $nombre === '') {
                $this->skipped++;
                $this->logError($excelRow, 'fila', 'Faltan campos obligatorios (codigo y/o nombre).', [
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                ]);
                return;
            }

            // Género (aceptamos "niña", "niño", F/M, unisex, etc.)
            $generoRaw = $this->firstString($R, ['genero', 'sexo', 'genero_nino', 'genero_niño', 'genero_objetivo']);
            $genero    = $this->normalizeGenero($generoRaw, $excelRow, $codigo);

            // Rango edad y otros numéricos (acepta alias y valores no numéricos -> log + default)
            $desdeV      = $this->firstString($R, ['desde', 'edad_desde', 'edad_min', 'inicio']);
            $hastaV      = $this->firstString($R, ['hasta', 'edad_hasta', 'edad_max', 'fin']);
            $unidadesV   = $this->firstString($R, ['unidades', 'cantidad', 'stock', 'existencias']);
            $porcentajeV = $this->firstString($R, ['porcentaje', 'porc', 'porcentaje_descuento']);

            $desde      = $this->toIntOrNull($desdeV);
            $hasta      = $this->toIntOrNull($hastaV);
            $unidades   = $this->toIntOrNull($unidadesV);
            $porcentaje = $this->toIntOrNull($porcentajeV);

            if ($desde === null) {
                $this->logError($excelRow, 'desde', 'Valor no numérico, se usó 0.', ['input' => $desdeV, 'codigo' => $codigo]);
                $desde = 0;
            }
            if ($hasta === null) {
                $this->logError($excelRow, 'hasta', 'Valor no numérico, se usó 0.', ['input' => $hastaV, 'codigo' => $codigo]);
                $hasta = 0;
            }
            if ($desde > $hasta) {
                $this->logError($excelRow, 'rango', 'Rango inválido (desde > hasta). Se intercambió.', [
                    'desde' => $desde,
                    'hasta' => $hasta,
                    'codigo' => $codigo
                ]);
                [$desde, $hasta] = [$hasta, $desde];
            }

            if ($unidades === null || $unidades < 0) {
                $this->logError($excelRow, 'unidades', 'Valor inválido, se usó 0.', ['input' => $unidadesV, 'codigo' => $codigo]);
                $unidades = 0;
            }
            if ($porcentaje === null || $porcentaje < 0 || $porcentaje > 100) {
                $this->logError($excelRow, 'porcentaje', 'Valor inválido, se usó 0.', ['input' => $porcentajeV, 'codigo' => $codigo]);
                $porcentaje = 0;
            }

            // ¿Combo?
            $isCombo = Str::contains($codigo, '+');

            // Imagen principal desde el código (asegurando .jpg)
            [$imagenppal, $partes] = $this->buildImagenPpalFromCodigo($codigo);

            // Verificar existencia de imágenes en storage público
            [$allExist, $missing, $checked] = $this->checkImages($partes);
            if (!$allExist) {
                foreach ($missing as $m) {
                    $this->logError($excelRow, 'imagenppal', 'Imagen no encontrada en storage.', [
                        'codigo' => $codigo,
                        'path'   => $m,
                    ]);
                }
            }

            $payload = [
                'combo'           => $isCombo ? 'COM' : 'NC',
                'idcampaign'      => $this->campaign->id,
                'referencia'      => $codigo,
                'nombre'          => $nombre,
                'imagenppal'      => $imagenppal,
                'genero'          => $genero,
                'desde'           => (string)$desde,
                'hasta'           => (string)$hasta,
                'unidades'        => $unidades,
                'precio_unitario' => 0,
                'porcentaje'      => (string)$porcentaje,
                'seleccionadas'   => 0,
                'imgexists'       => $allExist ? 'Y' : 'N',
                'descripcion'     => $descripcion,
                'escogidos'       => 0,
            ];

            // UPSERT por (idcampaign + referencia)
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
        } catch (\Throwable $e) {
            $this->skipped++;
            $this->logError(
                $excelRow,
                'exception',
                Str::limit($e->getMessage(), 255, ''),
                ['trace' => Str::limit($e->getFile() . ':' . $e->getLine(), 255, '')]
            );
        }
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
            'creados'      => $this->created,
            'actualizados' => $this->updated,
            'omitidos'     => $this->skipped,
        ];
    }
}
