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

class CampaignToysImport implements
    OnEachRow,
    WithHeadingRow,
    WithCalculatedFormulas,
    SkipsOnFailure,
    SkipsEmptyRows,
    WithBatchInserts,
    WithChunkReading
{
    use Importable, SkipsFailures;

    protected Campaign $campaign;
    protected ?string $jobId;
    protected int $created = 0;
    protected int $updated = 0;
    protected int $skipped = 0;

    /** Referencias realmente tocadas en esta importación */
    protected array $touchedRefs = []; // <<-- NUEVO

    public function __construct(Campaign $campaign, ?string $jobId = null)
    {
        $this->campaign  = $campaign;
        $this->jobId     = $jobId;
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

        $parts = $this->splitPlus($codigo);
        if (count($parts) > 1) {
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
     * Normaliza el porcentaje a STRING:
     * - No combo: "NN" (0..100).
     * - Combo: "n1+n2+...+nk" (cada n 0..100). Ajusta cantidad de términos a #partes.
     *   Acepta "%", decimales (., ,) y plus ancho "＋".
     */
    private function normalizePorcentaje(
        string $porcentajeRaw,
        bool $isCombo,
        int $partsCount,
        int $excelRow,
        string $codigo
    ): string {
        $raw = trim($porcentajeRaw);

        // parser de un valor: admite "44", "44.5", "44,5", "44%"
        $parseOne = function (string $val, int $idx = null) use ($excelRow, $codigo): int {
            $val = trim($val);
            $val = rtrim($val, " \t\n\r\0\x0B%");    // quita %
            $val = str_replace(',', '.', $val);      // coma decimal -> punto

            $n = null;
            if ($val !== '' && preg_match('/^-?\d+(\.\d+)?$/', $val)) {
                $n = (int) round((float) $val);
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
            if ($raw === '') {
                $this->logError($excelRow, 'porcentaje', 'Porcentaje vacío, se usó 0.', ['codigo' => $codigo]);
                return '0';
            }
            return (string) $parseOne($raw);
        }

        // Es combo
        $vals = $this->splitPlus($raw);

        if ($partsCount <= 0) {
            // Degradación segura: trata como no combo
            return (string) ($raw === '' ? 0 : $parseOne($raw));
        }

        // Ajusta cantidad de términos
        if (count($vals) !== $partsCount) {
            $this->logError(
                $excelRow,
                'porcentaje',
                "Cantidad de porcentajes no coincide con las partes del combo. Se ajustó a {$partsCount}.",
                ['codigo' => $codigo, 'porcentaje_raw' => $raw, 'partes' => $partsCount]
            );
            if (count($vals) > $partsCount) {
                $vals = array_slice($vals, 0, $partsCount);
            } else {
                while (count($vals) < $partsCount) {
                    $vals[] = '0';
                }
            }
        }

        // Normaliza cada parte (0..100)
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

            // normaliza keys
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

            // Detección de combo por código y/o porcentaje
            $codeParts    = $this->splitPlus($codigo);
            $isComboCode  = count($codeParts) > 1;

            $porcParts    = $this->splitPlus($porcentajeV);
            $isComboPorc  = count($porcParts) > 1;

            $isCombo = $isComboCode || $isComboPorc;

            // Imagen principal (partes desde código)
            [$imagenppal, $partes] = $this->buildImagenPpalFromCodigo($codigo);

            // Cantidad de partes para porcentaje (código si >1; si no, porcentaje si >1; si no, 1)
            $partsCountForPct = count($partes) > 1 ? count($partes) : ($isComboPorc ? count($porcParts) : 1);

            // Normaliza porcentaje (SIEMPRE STRING)
            $porcentajeStr = $this->normalizePorcentaje(
                porcentajeRaw: $porcentajeV,
                isCombo: $isCombo,
                partsCount: $partsCountForPct,
                excelRow: $excelRow,
                codigo: $codigo
            );

            // (No comprobamos existencia aquí; la descargará el Job MS Graph)
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
                'porcentaje'      => $porcentajeStr,   // <-- SIEMPRE string
                'seleccionadas'   => 0,
                'imgexists'       => 'N',              // lo actualizará DownloadCampaignToyImagesJob
                'descripcion'     => $descripcion,
                'escogidos'       => 0,
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

            // Marca referencia tocada (para filtrar descarga a solo estas)
            $this->touchedRefs[$codigo] = true; // <<-- NUEVO

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

    /** Devuelve las referencias tocadas (sin duplicados) */
    public function getTouchedRefs(): array // <<-- NUEVO
    {
        return array_keys($this->touchedRefs);
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

    /** Divide por “+” ASCII o “＋” (ancho completo), recorta y quita vacíos */
    private function splitPlus(string $s): array
    {
        $parts = preg_split('/[\+\xEF\xBC\x8B]/u', (string) $s);
        return array_values(array_filter(array_map('trim', $parts), fn($v) => $v !== ''));
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
