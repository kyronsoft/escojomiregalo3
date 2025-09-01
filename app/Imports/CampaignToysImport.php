<?php

namespace App\Imports;

use App\Models\Campaign;
use App\Models\CampaignToy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\Importable;
use Maatwebsite\Excel\Concerns\OnEachRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\SkipsFailures;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Row;

class CampaignToysImport implements OnEachRow, WithHeadingRow, WithCalculatedFormulas, SkipsOnFailure, SkipsEmptyRows, WithBatchInserts, WithChunkReading
{
    use Importable, SkipsFailures;

    protected Campaign $campaign;
    protected ?string $jobId;
    protected int $created = 0;
    protected int $updated = 0;
    protected int $skipped = 0;

    public function __construct(Campaign $campaign, ?string $jobId = null)
    {
        $this->campaign = $campaign;
        $this->jobId    = $jobId;
    }

    /** ================= Helpers ================= */

    private function rowIndex(Row $row): int
    {
        return (int) $row->getIndex();
    }

    /** Busca el primer valor no vacío entre alias de encabezados (normaliza como string) */
    private function firstString(array $arr, array $aliases): string
    {
        foreach ($aliases as $k) {
            if (!array_key_exists($k, $arr)) continue;
            $v = $arr[$k];
            if ($v === null) continue;
            $s = is_string($v) ? trim($v) : trim((string) $v);
            if ($s !== '') return $s;
        }
        return '';
    }

    /** Convierte a int seguro; si falla, devuelve null */
    private function toIntOrNull($v): ?int
    {
        if ($v === null || $v === '') return null;
        if (is_int($v)) return $v;
        if (is_float($v)) return (int) round($v);
        $s = is_string($v) ? trim($v) : (string) $v;
        if ($s === '') return null;
        if (!preg_match('/^-?\d+$/', $s)) return null;
        return (int) $s;
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

    /** Normaliza género a F/M/UNISEX */
    private function normalizeGenero(string $generoRaw, int $excelRow, string $codigo): string
    {
        $g = Str::lower(trim($generoRaw));
        if ($g === '') return 'UNISEX';

        if (Str::contains($g, 'niña')) return 'F';
        if (Str::contains($g, 'nino') || Str::contains($g, 'niño')) return 'M';

        if (in_array($g, ['f', 'm', 'unisex'], true)) {
            return strtoupper($g);
        }

        $this->logError($excelRow, 'genero', 'Valor de género no reconocido, se usó UNISEX.', [
            'codigo' => $codigo,
            'genero' => $generoRaw,
        ]);
        return 'UNISEX';
    }

    /** Asegura ".jpg" por parte */
    private function ensureJpg(string $name): string
    {
        $name = trim($name);
        if ($name === '') return $name;

        $lower = Str::lower($name);
        if (Str::endsWith($lower, '.jpg')) return $name;

        if (Str::contains($name, '.')) {
            $base = substr($name, 0, strrpos($name, '.'));
            return $base . '.jpg';
        }
        return $name . '.jpg';
    }

    /** Construye imagenppal asegurando .jpg; retorna [cadena, partes[]] */
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

    /** Verifica existencia de TODAS las imágenes */
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

    /**
     * Normaliza el porcentaje:
     * - No combo: devuelve "NN" (0..100) como string. Si inválido, 0 y log.
     * - Combo: acepta "n1+n2+...". Valida cantidad = #partes; corrige tamaño (trunca/pad con 0) y valida cada valor 0..100.
     *   Devuelve "n1+...+nk" normalizado como string. Registra errores cuando aplique.
     */
    private function normalizePorcentaje(
        string $porcentajeRaw,
        bool $isCombo,
        int $partsCount,
        int $excelRow,
        string $codigo
    ): string {
        $raw = trim($porcentajeRaw);

        // Helper para parsear un valor a 0..100 (int), default 0 con log
        $parseOne = function (string $val, int $idx = null) use ($excelRow, $codigo): int {
            $val = trim($val);
            // quitar posibles símbolos %
            $val = rtrim($val, " \t\n\r\0\x0B%");

            $n = null;
            if ($val !== '' && preg_match('/^-?\d+(\.\d+)?$/', $val)) {
                // aceptamos decimal pero redondeamos a int
                $f = (float) $val;
                $n = (int) round($f);
            }

            if ($n === null || $n < 0 || $n > 100) {
                $this->logError(
                    $excelRow,
                    'porcentaje',
                    'Valor de porcentaje inválido, se usó 0.',
                    ['codigo' => $codigo, 'input' => $val, 'parte' => $idx]
                );
                return 0;
            }

            return $n;
        };

        if (!$isCombo) {
            // 1 solo valor
            if ($raw === '') {
                $this->logError($excelRow, 'porcentaje', 'Porcentaje vacío, se usó 0.', ['codigo' => $codigo]);
                return '0';
            }
            $n = $parseOne($raw);
            return (string) $n;
        }

        // Es combo
        $vals = array_map('trim', explode('+', $raw));
        $vals = array_values(array_filter($vals, fn($v) => $v !== '')); // quita vacíos explícitos

        if ($partsCount <= 0) {
            // Debería no ocurrir; por si acaso, tratamos como no-combo
            if ($raw === '') return '0';
            $n = $parseOne($raw);
            return (string) $n;
        }

        // Ajuste de tamaño: debe coincidir con #partes
        if (count($vals) !== $partsCount) {
            $this->logError(
                $excelRow,
                'porcentaje',
                "Cantidad de porcentajes no coincide con las partes del combo. Se ajustó a {$partsCount}.",
                ['codigo' => $codigo, 'porcentaje_raw' => $porcentajeRaw, 'partes' => $partsCount]
            );
            if (count($vals) > $partsCount) {
                $vals = array_slice($vals, 0, $partsCount);
            } else {
                // pad con "0" hasta partsCount
                while (count($vals) < $partsCount) {
                    $vals[] = '0';
                }
            }
        }

        // Normalizar cada parte 0..100
        $norm = [];
        foreach ($vals as $i => $v) {
            $norm[] = (string) $parseOne($v, $i + 1);
        }

        return implode('+', $norm);
    }

    /** ================= Import logic ================= */

    public function onRow(Row $row)
    {
        $excelRow = $this->rowIndex($row);

        try {
            $raw = $row->toArray();
            $R = [];
            foreach ($raw as $k => $v) {
                $key = Str::of($k)->lower()->replace(' ', '_')->replace('__', '_')->value();
                $R[$key] = $v;
            }

            $codigo      = $this->firstString($R, ['codigo', 'referencia', 'cod_ref', 'cod_referencia', 'sku', 'codigo_referencia']);
            $nombre      = $this->firstString($R, ['nombre', 'nombre_producto', 'producto', 'descripcion_corta', 'titulo']);
            $descripcion = $this->firstString($R, ['descripcion', 'descripcion_larga', 'detalle', 'caracteristicas', 'observaciones']);

            if ($codigo === '' || $nombre === '') {
                $this->skipped++;
                $this->logError($excelRow, 'fila', 'Faltan campos obligatorios (codigo y/o nombre).', compact('codigo', 'nombre'));
                $this->tick(1);
                return;
            }

            $generoRaw = $this->firstString($R, ['genero', 'sexo', 'genero_nino', 'genero_niño', 'genero_objetivo']);
            $genero    = $this->normalizeGenero($generoRaw, $excelRow, $codigo);

            $desdeV      = $this->firstString($R, ['desde', 'edad_desde', 'edad_min', 'inicio']);
            $hastaV      = $this->firstString($R, ['hasta', 'edad_hasta', 'edad_max', 'fin']);
            $unidadesV   = $this->firstString($R, ['unidades', 'cantidad', 'stock', 'existencias']);
            $porcentajeV = $this->firstString($R, ['porcentaje', 'porc', 'porcentaje_descuento']);

            $desde = $this->toIntOrNull($desdeV) ?? 0;
            $hasta = $this->toIntOrNull($hastaV) ?? 0;
            if ($this->toIntOrNull($desdeV) === null) $this->logError($excelRow, 'desde', 'Valor no numérico, se usó 0.', ['input' => $desdeV, 'codigo' => $codigo]);
            if ($this->toIntOrNull($hastaV) === null) $this->logError($excelRow, 'hasta', 'Valor no numérico, se usó 0.', ['input' => $hastaV, 'codigo' => $codigo]);
            if ($desde > $hasta) {
                $this->logError($excelRow, 'rango', 'Rango inválido (desde > hasta). Se intercambió.', compact('desde', 'hasta', 'codigo'));
                [$desde, $hasta] = [$hasta, $desde];
            }

            $unidades = $this->toIntOrNull($unidadesV);
            if ($unidades === null || $unidades < 0) {
                $unidades = 0;
                $this->logError($excelRow, 'unidades', 'Valor inválido, se usó 0.', ['input' => $unidadesV, 'codigo' => $codigo]);
            }

            $isCombo = Str::contains($codigo, '+');
            [$imagenppal, $partes] = $this->buildImagenPpalFromCodigo($codigo);

            // Porcentaje STRING (maneja combos con "+")
            $porcentajeStr = $this->normalizePorcentaje(
                porcentajeRaw: $porcentajeV,
                isCombo: $isCombo,
                partsCount: count($partes),
                excelRow: $excelRow,
                codigo: $codigo
            );

            // No forzamos existencia aquí; las descargará el Job de Microsoft Graph
            $payload = [
                'combo'           => $isCombo ? 'COM' : 'NC',
                'idcampaign'      => $this->campaign->id,
                'referencia'      => $codigo,
                'nombre'          => $nombre,
                'imagenppal'      => $imagenppal,
                'genero'          => $genero,
                'desde'           => (string) $desde,
                'hasta'           => (string) $hasta,
                'unidades'        => $unidades,
                'precio_unitario' => 0,
                'porcentaje'      => $porcentajeStr,   // <-- SIEMPRE string, con "+" para combos
                'seleccionadas'   => 0,
                'imgexists'       => 'N',              // será actualizado por DownloadCampaignToyImagesJob
                'descripcion'     => $descripcion,
                'escogidos'       => 0,
            ];

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
        } finally {
            $this->tick(1);
        }
    }

    private function tick(int $n): void
    {
        if (!$this->jobId) return;

        $key   = self::progressKey($this->jobId);
        $state = Cache::get($key, []);
        $meta  = $state['meta'] ?? [];
        $done  = (int) ($meta['processed_records'] ?? 0);
        $meta['processed_records'] = $done + $n;

        $state['meta'] = $meta;
        Cache::put($key, $state, now()->addHours(2));
    }

    public static function progressKey(string $jobId): string
    {
        return "campaign_toys:import:progress:{$jobId}";
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
