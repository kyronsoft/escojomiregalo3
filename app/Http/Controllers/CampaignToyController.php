<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignToy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CampaignToyController extends Controller
{
    // === Vista con paginación clásica (si la usas en otra ruta/menu) ===
    public function index(Request $request)
    {
        $q = CampaignToy::query()->with('campaign:id,nombre');

        if ($request->filled('idcampaign'))
            $q->where('idcampaign', (int) $request->input('idcampaign'));
        if ($request->filled('referencia'))
            $q->where('referencia', 'like', '%' . $request->input('referencia') . '%');
        if ($request->filled('nombre'))
            $q->where('nombre', 'like', '%' . $request->input('nombre') . '%');

        $toys = $q->latest('updated_at')->paginate(15)->withQueryString();

        return view('campaign_toys.index', compact('toys'));
    }

    // === Endpoint para Tabulator (AJAX) ===
    public function data(Request $request)
    {
        $q = CampaignToy::query()->with('campaign:id,nombre');

        if ($request->filled('idcampaign'))
            $q->where('idcampaign', (int) $request->input('idcampaign'));
        if ($request->filled('referencia'))
            $q->where('referencia', 'like', '%' . $request->input('referencia') . '%');
        if ($request->filled('nombre'))
            $q->where('nombre', 'like', '%' . $request->input('nombre') . '%');

        $rows = $q->latest('updated_at')->get([
            'id',
            'idcampaign',
            'referencia',
            'nombre',
            'imagenppal',
            'genero',
            'unidades',
            'precio_unitario',
            'porcentaje',
            'updated_at',
        ]);

        $out = $rows->map(function (CampaignToy $t) {
            return [
                'id' => $t->id,
                'idcampaign' => $t->idcampaign,
                'referencia' => $t->referencia,
                'nombre' => $t->nombre,
                'imagenppal' => $t->imagenppal,
                'image_url' => $t->image_url ?? $this->publicUrlIfExists($t->imagenppal),
                'image_parts_count' => $t->image_parts_count ?? null,
                'genero' => $t->genero,
                'unidades' => $t->unidades,
                'precio_unitario' => $t->precio_unitario,
                'porcentaje' => $t->porcentaje,
                'updated_at' => $t->updated_at,
                'campaign_nombre' => optional($t->campaign)->nombre,
            ];
        });

        return response()->json($out);
    }

    public function show(Campaign $campaign, CampaignToy $toy)
    {
        if ((int) $toy->idcampaign !== (int) $campaign->id)
            abort(404);

        $parts = $this->splitRefs((string) $toy->referencia);
        $imageMap = [];
        foreach ($parts as $ref) {
            $imageMap[$ref] = $this->findPublicUrlForRefInFolder($campaign->id, $ref);
        }

        $toy->image_map = $imageMap;
        $toy->image_url = $this->toyImagePublicUrl($toy->imagenppal, $campaign);

        if (!empty($toy->imagenppal)) {
            $defaultSp = $toy->imagenppal;
        } else {
            $withExt = array_map(function ($r) {
                $r = trim($r);
                if ($r === '')
                    return $r;
                if (preg_match('/\.(jpg|jpeg|png)$/i', $r)) {
                    return $r;
                }
                return $r . '.jpg';
            }, $parts);

            $defaultSp = implode('+', $withExt);
        }

        return view('campaigns.toys.show', compact('campaign', 'toy', 'defaultSp'));
    }

    public function edit(Campaign $campaign, CampaignToy $toy)
    {
        if ((int) $toy->idcampaign !== (int) $campaign->id)
            abort(404);

        $parts = $this->splitRefs((string) $toy->referencia);
        $imageMap = [];
        foreach ($parts as $ref) {
            $imageMap[$ref] = $this->findPublicUrlForRefInFolder($campaign->id, $ref);
        }

        $toy->image_map = $imageMap;
        $toy->image_url = $this->toyImagePublicUrl($toy->imagenppal, $campaign);

        if (!$toy->image_url && count($parts) > 0) {
            $first = reset($parts);
            $fallback = $this->findPublicUrlForRefInFolder($campaign->id, $first);
            if ($fallback) {
                $toy->image_url = $fallback;
            }
        }

        // 🔴 AQUÍ VIENE LO IMPORTANTE
        // 1) Si imagenppal tiene valor, usa EXACTAMENTE lo que está en BD.
        // 2) Si está vacío, construye a partir de referencia, respetando cada parte.
        if (!empty($toy->imagenppal)) {
            $defaultSp = old('sp_path', $toy->imagenppal);
        } else {
            $withExt = array_map(function ($r) {
                $r = trim($r);
                if ($r === '')
                    return $r;
                // Si ya tiene extensión, respétala
                if (preg_match('/\.(jpg|jpeg|png)$/i', $r)) {
                    return $r;
                }
                // Si no, asume .jpg
                return $r . '.jpg';
            }, $parts);

            $defaultSp = old('sp_path', implode('+', $withExt));
        }

        return view('campaigns.toys.edit', compact('campaign', 'toy', 'defaultSp'));
    }

    public function update(Request $request, Campaign $campaign, CampaignToy $toy)
    {
        if ((int) $toy->idcampaign !== (int) $campaign->id) {
            abort(404);
        }

        $rules = [
            'referencia' => ['required', 'string', 'max:190'],
            'nombre' => ['required', 'string', 'max:255'],
            'genero' => ['nullable', 'string', 'max:50'],
            'unidades' => ['nullable', 'integer', 'min:0'],
            'precio_unitario' => ['nullable', 'numeric', 'min:0'],
            'porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'imagenppal' => ['nullable', 'string', 'max:2048'],
            // 👇 Edades
            'desde' => ['nullable', 'integer', 'min:0', 'max:120'],
            'hasta' => ['nullable', 'integer', 'min:0', 'max:120'],
        ];

        $validator = Validator::make($request->all(), $rules);

        // Validación cruzada: hasta >= desde (si ambos vienen)
        $validator->after(function ($v) use ($request) {
            $d = $request->input('desde');
            $h = $request->input('hasta');
            if ($d !== null && $h !== null) {
                if ((int) $h < (int) $d) {
                    $v->errors()->add('hasta', 'La edad "hasta" debe ser mayor o igual que "desde".');
                }
            }
        });

        $validated = $validator->validate();
        $validated['idcampaign'] = $campaign->id;

        // Asignar asegurando enteros para edades cuando existan
        $toy->fill([
            'idcampaign' => $validated['idcampaign'],
            'referencia' => $validated['referencia'],
            'nombre' => $validated['nombre'],
            'genero' => $validated['genero'] ?? $toy->genero,
            'unidades' => array_key_exists('unidades', $validated) ? $validated['unidades'] : $toy->unidades,
            'precio_unitario' => array_key_exists('precio_unitario', $validated) ? $validated['precio_unitario'] : $toy->precio_unitario,
            'porcentaje' => array_key_exists('porcentaje', $validated) ? $validated['porcentaje'] : $toy->porcentaje,
            'imagenppal' => $validated['imagenppal'] ?? $toy->imagenppal,
            'desde' => array_key_exists('desde', $validated) ? (int) $validated['desde'] : $toy->desde,
            'hasta' => array_key_exists('hasta', $validated) ? (int) $validated['hasta'] : $toy->hasta,
        ]);

        $toy->save();

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok' => true,
                'message' => 'Juguete actualizado correctamente.',
                'toy' => $toy->only([
                    'id',
                    'idcampaign',
                    'referencia',
                    'nombre',
                    'genero',
                    'unidades',
                    'precio_unitario',
                    'porcentaje',
                    'imagenppal',
                    'desde',
                    'hasta',
                ]),
            ]);
        }

        return redirect()
            ->route('campaigns.toys.edit', ['campaign' => $campaign->id, 'toy' => $toy->id])
            ->with('success', 'Juguete actualizado correctamente.');
    }

    // === Eliminar (anidado) ===
    public function destroy(Campaign $campaign, CampaignToy $toy)
    {
        if ((int) $toy->idcampaign !== (int) $campaign->id) {
            return response()->json(['ok' => false, 'message' => 'El juguete no pertenece a la campaña.'], 404);
        }

        try {
            $toy->delete();

            return response()->json([
                'ok' => true,
                'message' => 'Juguete eliminado correctamente.'
            ], 200);
        } catch (\Throwable $e) {
            \Log::error('Error al eliminar juguete', [
                'toy_id' => $toy->id,
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok' => false,
                'message' => 'No se pudo eliminar el registro.'
            ], 500);
        }
    }

    /**
     * Descarga desde el share configurado (MSGRAPH_SHARE_URL) a /public/campaign_toys/{campaign}/
     * Busca por Graph: search + crawl dentro de ese share. No usa otros drives/sitios.
     */
    public function fetchImageFromSharePoint(
        Request $request,
        Campaign $campaign,
        CampaignToy $toy
    ) {
        if ((int) $toy->idcampaign !== (int) $campaign->id) {
            return response()->json(['ok' => false, 'message' => 'Toy no pertenece a la campaña'], 404);
        }

        $request->validate(['sp_path' => ['nullable', 'string', 'max:2048']]);

        $ctx = $this->resolveSpContext();  // shareUrl

        // ✅ Si no viene sp_path en el request, usa imagenppal como default (mantiene el mismo valor que ves en Blade)
        $rawPath = trim((string) $request->input('sp_path', $toy->imagenppal ?? ''));

        $folderLocal = "campaign_toys/{$campaign->id}";
        Storage::disk('public')->makeDirectory($folderLocal);

        $defaultRefs = $this->splitRefs((string) $toy->referencia);
        $items = $this->expandSharePointItems($rawPath, $defaultRefs);

        $results = [];
        $okCount = 0;
        $failCount = 0;

        // Un solo recorrido del share (mismo método que el job de importación)
        try {
            $index = $this->shareIndex((string) $ctx['shareUrl']);
        } catch (\Throwable $e) {
            \Log::error('No se pudo listar el share de Graph', ['error' => $e->getMessage()]);
            return response()->json([
                'ok' => false,
                'message' => 'No se pudo consultar el share configurado: ' . $e->getMessage(),
            ], 502);
        }

        \Log::debug('SP fetch request', [
            'campaign' => $campaign->id,
            'toy' => $toy->id,
            'refs' => $defaultRefs,
            'items' => $items,
            'ctx' => $ctx,
            'rawPath' => $rawPath,
        ]);

        foreach ($items as $it) {
            $refLabel = $it['label'] ?? null;

            [$ok, $res] = $this->downloadFromIndex($index, $it, $folderLocal, $refLabel);

            $results[] = $res;
            $ok ? $okCount++ : $failCount++;
        }

        \Log::debug('SP fetch results', [
            'okCount' => $okCount,
            'failCount' => $failCount,
            'results' => $results,
        ]);

        if ($okCount > 0) {
            // imagenppal guarda solo nombre(s) de archivo, no ruta: "a.jpg" o combo "a.jpg+b.jpg".
            $fileNames = collect($results)
                ->filter(fn ($r) => !empty($r['ok']) && !empty($r['local_rel']))
                ->map(fn ($r) => basename($r['local_rel']))
                ->values()
                ->all();

            if (!empty($fileNames)) {
                $toy->imagenppal = implode('+', $fileNames);
                $toy->save();
            }
        }

        return response()->json([
            'ok' => $okCount > 0,
            'summary' => ['ok' => $okCount, 'fail' => $failCount, 'total' => count($results)],
            'results' => $results,
        ]);
    }

    // ---------- Helpers de nombres/refs/descargas ----------

    private function splitRefs(string $ref): array
    {
        $parts = array_values(array_filter(array_map('trim', explode('+', $ref))));
        return $parts ?: [$ref];
    }

    /**
     * Construye el valor por defecto de sp_path:
     * - Si imagenppal tiene algo (incluyendo '+'), lo respeta tal cual.
     * - Si no, arma un string uniendo las referencias por '+'.
     */
    private function buildDefaultSpPath(CampaignToy $toy): string
    {
        $img = trim((string) $toy->imagenppal);
        if ($img !== '') {
            return $img;
        }

        $refs = $this->splitRefs((string) $toy->referencia);
        if (!empty($refs)) {
            return implode('+', $refs);
        }

        return '';
    }

    private function findPublicUrlForRefInFolder(int $campaignId, string $ref): ?string
    {
        // carpeta lógica donde guardas
        $base = "campaign_toys/{$campaignId}/";

        // normalizador: quita espacios, guiones, _, puntos y pasa a minúscula
        $norm = function (string $s): string {
            $s = strtolower($s);
            // quita extensión
            $s = pathinfo($s, PATHINFO_FILENAME);
            // deja solo letras y números
            return preg_replace('/[^a-z0-9]/', '', $s) ?? '';
        };

        $refNorm = $norm($ref);

        // 1) Búsqueda directa por nombres esperados
        foreach (['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'] as $ext) {
            $rel = $base . $ref . '.' . $ext;
            if (Storage::disk('public')->exists($rel)) {
                return Storage::disk('public')->url($rel);
            }
        }

        // 2) Búsqueda por prefijo normalizado más flexible
        $files = Storage::disk('public')->files($base);
        foreach ($files as $f) {
            $filename = pathinfo($f, PATHINFO_BASENAME);
            $nameNorm = $norm($filename);

            // coincide si el "inicio" del nombre es el ref normalizado
            if ($refNorm !== '' && str_starts_with($nameNorm, $refNorm)) {
                return Storage::disk('public')->url($f);
            }
        }

        // 3) Plan C: si guardaste directamente en public_path (no recomendado), intenta construir URL
        $publicAbs = public_path($base);
        if (is_dir($publicAbs)) {
            foreach (glob($publicAbs . '*') as $abs) {
                $basename = basename($abs);
                $nameNorm = $norm($basename);
                if ($refNorm !== '' && str_starts_with($nameNorm, $refNorm)) {
                    // Devuelve como /campaign_toys/{id}/{archivo}
                    return '/' . trim($base, '/') . '/' . $basename;
                }
            }
        }

        return null;
    }

    private function toyImagePublicUrl(?string $value, Campaign $campaign): ?string
    {
        if (empty($value))
            return null;
        if (preg_match('#^https?://#i', $value))
            return $value;

        // Normaliza separadores y quita prefijos comunes
        $p = str_replace('\\', '/', $value);
        $p = ltrim($p, '/');
        $p = preg_replace('#^(storage/app/)?public/#i', '', $p);  // quita "storage/app/public/" o "public/"

        // 1) Si existe en el disco 'public', arma la URL del disco
        if (Storage::disk('public')->exists($p)) {
            return Storage::disk('public')->url($p);
        }

        // 2) Si no tenía subcarpeta, intenta en la carpeta del campaign
        if (!str_contains($p, '/')) {
            $guess = "campaign_toys/{$campaign->id}/{$p}";
            if (Storage::disk('public')->exists($guess)) {
                return Storage::disk('public')->url($guess);
            }
        }

        // 3) Fallback: si físicamente está en public_path
        $abs = public_path($p);
        if (is_file($abs)) {
            return '/' . $p;
        }

        return null;
    }

    private function publicUrlIfExists(?string $rel): ?string
    {
        if (!$rel)
            return null;
        $p = ltrim(str_replace('\\', '/', $rel), '/');
        if (preg_match('#^https?://#i', $p))
            return $p;
        if (preg_match('#^storage/#i', $p))
            return '/' . $p;
        return Storage::disk('public')->exists($p) ? Storage::disk('public')->url($p) : null;
    }

    private function resolveSpContext(): array
    {
        return [
            'shareUrl' => config('services.msgraph.share_url', env('MSGRAPH_SHARE_URL')),
        ];
    }

    private function expandSharePointItems(string $rawPath, array $defaultRefs): array
    {
        $out = [];
        $makeCandidates = function (string $base) {
            $b = trim($base, " \t\n\r\0\v/\\");
            return [
                'label' => pathinfo($b, PATHINFO_FILENAME) ?: $b,
                'remoteCandidates' => [$b . '.jpg', $b . '.jpeg', $b . '.png'],
                'localNameCandidates' => [$b . '.jpg', $b . '.jpeg', $b . '.png'],
            ];
        };

        if ($rawPath !== '') {
            foreach (explode('+', $rawPath) as $part) {
                $part = trim($part);
                if ($part === '')
                    continue;

                if (preg_match('/\.(jpg|jpeg|png)$/i', $part)) {
                    $out[] = [
                        'label' => pathinfo($part, PATHINFO_FILENAME),
                        'remote' => $part,
                        'localName' => basename($part),
                    ];
                } else {
                    $out[] = $makeCandidates($part);
                }
            }
            return $out;
        }

        foreach ($defaultRefs as $ref) {
            $ref = trim($ref);
            if ($ref === '')
                continue;
            $out[] = $makeCandidates($ref);
        }
        return $out;
    }

    /**
     * Lista el share configurado una sola vez: nombre (minúsculas) => downloadUrl.
     */
    private function shareIndex(string $shareUrl): array
    {
        $key = 'campaign_toys:share_index:' . sha1($shareUrl);
        $cached = \Cache::get($key);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $graph = app(\App\Services\MsGraphClient::class);
        $token = $graph->getToken();

        $index = [];
        foreach ($graph->listAllSharedChildren($shareUrl, $token) as $f) {
            $name = trim((string) ($f['name'] ?? ''));
            if ($name === '' || empty($f['downloadUrl'])) {
                continue;
            }
            $index[mb_strtolower($name)] = $f['downloadUrl'];
        }

        // Solo se cachea un índice válido; una lista vacía no debe quedar guardada.
        if ($index !== []) {
            \Cache::put($key, $index, now()->addMinutes(30));
        }

        return $index;
    }

    /**
     * Descarga un ítem usando el índice del share. Prueba los nombres candidatos en orden.
     *
     * @return array{0:bool,1:array}
     */
    private function downloadFromIndex(array $index, array $it, string $folderLocal, ?string $refLabel): array
    {
        if (!empty($it['remote'])) {
            $pairs = [[$it['remote'], $it['localName'] ?? basename($it['remote'])]];
        } else {
            $pairs = [];
            foreach ($it['remoteCandidates'] ?? [] as $i => $cand) {
                $pairs[] = [$cand, $it['localNameCandidates'][$i] ?? basename($cand)];
            }
        }

        $queried = [];
        foreach ($pairs as [$remoteName, $localName]) {
            $lookup = basename(trim($remoteName));
            $queried[] = $lookup;

            $url = $index[mb_strtolower($lookup)] ?? null;
            if ($url === null) {
                continue;
            }

            $destRel = rtrim($folderLocal, '/') . '/' . $localName;
            $destAbs = storage_path('app/public/' . $destRel);
            @mkdir(dirname($destAbs), 0775, true);

            try {
                $resp = \Illuminate\Support\Facades\Http::timeout(60)->sink($destAbs)->get($url);
                if (!$resp->successful()) {
                    throw new \RuntimeException('Descarga respondió HTTP ' . $resp->status());
                }
            } catch (\Throwable $e) {
                @unlink($destAbs); // no dejar cuerpos de error como si fueran imágenes
                \Log::warning('Error descargando imagen del share', ['file' => $lookup, 'error' => $e->getMessage()]);
                return [false, ['ok' => false, 'ref' => $refLabel, 'query' => $lookup, 'source' => 'share-index', 'message' => $e->getMessage()]];
            }

            return [true, [
                'ok' => true,
                'ref' => $refLabel,
                'query' => $lookup,
                'local_rel' => $destRel,
                'image_url' => Storage::disk('public')->url($destRel),
                'source' => 'share-index',
            ]];
        }

        return [false, [
            'ok' => false,
            'ref' => $refLabel,
            'query' => implode(' | ', $queried),
            'source' => 'share-index',
            'message' => 'No se encontró el archivo en el share configurado.',
        ]];
    }

}
