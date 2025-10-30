<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignToy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Validator;


class CampaignToyController extends Controller
{
    // === Vista con paginación clásica (si la usas en otra ruta/menu) ===
    public function index(Request $request)
    {
        $q = CampaignToy::query()->with('campaign:id,nombre');

        if ($request->filled('idcampaign')) $q->where('idcampaign', (int) $request->input('idcampaign'));
        if ($request->filled('referencia')) $q->where('referencia', 'like', '%' . $request->input('referencia') . '%');
        if ($request->filled('nombre'))     $q->where('nombre',     'like', '%' . $request->input('nombre')     . '%');

        $toys = $q->latest('updated_at')->paginate(15)->withQueryString();

        return view('campaign_toys.index', compact('toys'));
    }

    // === Endpoint para Tabulator (AJAX) ===
    public function data(Request $request)
    {
        $q = CampaignToy::query()->with('campaign:id,nombre');

        if ($request->filled('idcampaign')) $q->where('idcampaign', (int) $request->input('idcampaign'));
        if ($request->filled('referencia')) $q->where('referencia', 'like', '%' . $request->input('referencia') . '%');
        if ($request->filled('nombre'))     $q->where('nombre',     'like', '%' . $request->input('nombre')     . '%');

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
                'id'                => $t->id,
                'idcampaign'        => $t->idcampaign,
                'referencia'        => $t->referencia,
                'nombre'            => $t->nombre,
                'imagenppal'        => $t->imagenppal,
                'image_url'         => $t->image_url ?? $this->publicUrlIfExists($t->imagenppal),
                'image_parts_count' => $t->image_parts_count ?? null,
                'genero'            => $t->genero,
                'unidades'          => $t->unidades,
                'precio_unitario'   => $t->precio_unitario,
                'porcentaje'        => $t->porcentaje,
                'updated_at'        => $t->updated_at,
                'campaign_nombre'   => optional($t->campaign)->nombre,
            ];
        });

        return response()->json($out);
    }

    // === Mostrar detalle (nuevo) ===
    public function show(Campaign $campaign, CampaignToy $toy)
    {
        if ((int) $toy->idcampaign !== (int) $campaign->id) abort(404);

        $parts = $this->splitRefs((string) $toy->referencia);
        $imageMap = [];
        foreach ($parts as $ref) {
            $imageMap[$ref] = $this->findPublicUrlForRefInFolder($campaign->id, $ref);
        }

        $toy->image_map = $imageMap;
        $toy->image_url = $this->toyImagePublicUrl($toy->imagenppal, $campaign);

        // Crea esta vista o reutiliza edit si quieres: resources/views/campaigns/toys/show.blade.php
        return view('campaigns.toys.show', compact('campaign', 'toy'));
    }

    // === Editar existente (ya lo tenías) ===
    public function edit(Campaign $campaign, CampaignToy $toy)
    {
        if ((int) $toy->idcampaign !== (int) $campaign->id) abort(404);

        $parts = $this->splitRefs((string) $toy->referencia);
        $imageMap = [];
        foreach ($parts as $ref) {
            $imageMap[$ref] = $this->findPublicUrlForRefInFolder($campaign->id, $ref);
        }

        $toy->image_map = $imageMap;
        $toy->image_url = $this->toyImagePublicUrl($toy->imagenppal, $campaign);

        // 👉 Si no hay imagen principal, intenta con la primera parte
        if (!$toy->image_url && count($parts) > 0) {
            $first = reset($parts);
            $fallback = $this->findPublicUrlForRefInFolder($campaign->id, $first);
            if ($fallback) {
                $toy->image_url = $fallback;
            }
        }

        return view('campaigns.toys.edit', compact('campaign', 'toy'));
    }

    public function update(Request $request, Campaign $campaign, CampaignToy $toy)
    {
        if ((int) $toy->idcampaign !== (int) $campaign->id) {
            abort(404);
        }

        $rules = [
            'referencia'       => ['required', 'string', 'max:190'],
            'nombre'           => ['required', 'string', 'max:255'],
            'genero'           => ['nullable', 'string', 'max:50'],
            'unidades'         => ['nullable', 'integer', 'min:0'],
            'precio_unitario'  => ['nullable', 'numeric', 'min:0'],
            'porcentaje'       => ['nullable', 'numeric', 'min:0', 'max:100'],
            'imagenppal'       => ['nullable', 'string', 'max:2048'],
            // 👇 Edades
            'desde'            => ['nullable', 'integer', 'min:0', 'max:120'],
            'hasta'            => ['nullable', 'integer', 'min:0', 'max:120'],
        ];

        $validator = Validator::make($request->all(), $rules);

        // Validación cruzada: hasta >= desde (si ambos vienen)
        $validator->after(function ($v) use ($request) {
            $d = $request->input('desde');
            $h = $request->input('hasta');
            if ($d !== null && $h !== null) {
                if ((int)$h < (int)$d) {
                    $v->errors()->add('hasta', 'La edad "hasta" debe ser mayor o igual que "desde".');
                }
            }
        });

        $validated = $validator->validate();
        $validated['idcampaign'] = $campaign->id;

        // Asignar asegurando enteros para edades cuando existan
        $toy->fill([
            'idcampaign'      => $validated['idcampaign'],
            'referencia'      => $validated['referencia'],
            'nombre'          => $validated['nombre'],
            'genero'          => $validated['genero'] ?? $toy->genero,
            'unidades'        => array_key_exists('unidades', $validated) ? $validated['unidades'] : $toy->unidades,
            'precio_unitario' => array_key_exists('precio_unitario', $validated) ? $validated['precio_unitario'] : $toy->precio_unitario,
            'porcentaje'      => array_key_exists('porcentaje', $validated) ? $validated['porcentaje'] : $toy->porcentaje,
            'imagenppal'      => $validated['imagenppal'] ?? $toy->imagenppal,
            'desde'           => array_key_exists('desde', $validated) ? (int)$validated['desde'] : $toy->desde,
            'hasta'           => array_key_exists('hasta', $validated) ? (int)$validated['hasta'] : $toy->hasta,
        ]);

        $toy->save();

        if ($request->expectsJson() || $request->wantsJson() || $request->ajax()) {
            return response()->json([
                'ok'      => true,
                'message' => 'Juguete actualizado correctamente.',
                'toy'     => $toy->only([
                    'id','idcampaign','referencia','nombre','genero',
                    'unidades','precio_unitario','porcentaje','imagenppal',
                    'desde','hasta',
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
        if ((int)$toy->idcampaign !== (int)$campaign->id) {
            return response()->json(['ok' => false, 'message' => 'El juguete no pertenece a la campaña.'], 404);
        }

        try {
            // (Opcional) Limpieza de archivos relacionados
            // $folder = "campaign_toys/{$campaign->id}";
            // $refBase = pathinfo((string)$toy->referencia, PATHINFO_FILENAME);
            // if (Storage::disk('public')->exists($folder)) {
            //     foreach (Storage::disk('public')->files($folder) as $f) {
            //         $name = pathinfo($f, PATHINFO_FILENAME);
            //         if (stripos($name, $refBase) === 0) Storage::disk('public')->delete($f);
            //     }
            // }

            $toy->delete();

            return response()->json([
                'ok'      => true,
                'message' => 'Juguete eliminado correctamente.'
            ], 200);
        } catch (\Throwable $e) {
            \Log::error('Error al eliminar juguete', [
                'toy_id' => $toy->id,
                'campaign_id' => $campaign->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'ok'      => false,
                'message' => 'No se pudo eliminar el registro.'
            ], 500);
        }
    }

    /**
     * Descarga desde SharePoint a /public/campaign_toys/{campaign}/
     * Si no encuentra por ruta, cae a Graph: search + crawl.
     */
    public function fetchImageFromSharePoint(
        Request $request,
        Campaign $campaign,
        CampaignToy $toy,
        \App\Services\SharePointDownloader $sp
    ) {
        if ((int)$toy->idcampaign !== (int)$campaign->id) {
            return response()->json(['ok' => false, 'message' => 'Toy no pertenece a la campaña'], 404);
        }

        $request->validate(['sp_path' => ['nullable', 'string', 'max:2048']]);

        $ctx = $this->resolveSpContext(); // shareUrl/siteId/driveId
        $ctx = $this->ensureGraphIds($ctx); 
        $rawPath     = trim((string)$request->input('sp_path', ''));
        $folderLocal = "campaign_toys/{$campaign->id}";
        Storage::disk('public')->makeDirectory($folderLocal);

        $defaultRefs = $this->splitRefs((string)$toy->referencia);
        $items       = $this->expandSharePointItems($rawPath, $defaultRefs);

        $results   = [];
        $okCount   = 0;
        $failCount = 0;

        \Log::debug('SP fetch request', [
            'campaign' => $campaign->id,
            'toy'      => $toy->id,
            'refs'     => $defaultRefs,
            'items'    => $items,
            'ctx'      => $ctx,
        ]);

        foreach ($items as $it) {
            $refLabel = $it['label'] ?? null;

            // 1) Servicio por ruta
            $serviceOK  = false;
            $serviceRes = null;

            if (!empty($it['remote'])) {
                [$serviceOK, $serviceRes] = $this->tryServiceDownloadOne(
                    $sp,
                    ltrim($it['remote'], '/'),
                    $folderLocal,
                    $it['localName'],
                    $refLabel
                );
            } elseif (!empty($it['remoteCandidates'])) {
                foreach ($it['remoteCandidates'] as $i => $cand) {
                    $localName = $it['localNameCandidates'][$i] ?? basename($cand);
                    [$serviceOK, $serviceRes] = $this->tryServiceDownloadOne(
                        $sp,
                        ltrim($cand, '/'),
                        $folderLocal,
                        $localName,
                        $refLabel
                    );
                    if ($serviceOK) break;
                }
            }

            if ($serviceOK) {
                $results[] = $serviceRes;
                $okCount++;
                continue;
            }

            // 2) Fallback Graph
            [$graphOK, $graphRes] = $this->tryGraphFindAndDownload(
                $ctx,
                $it,
                $folderLocal,
                $refLabel
            );

            $results[] = $graphRes;
            $graphOK ? $okCount++ : $failCount++;
        }

        \Log::debug('SP fetch results', [
            'okCount' => $okCount,
            'failCount' => $failCount,
            'results' => $results,
        ]);

        if ($okCount > 0) {
            $firstOk = collect($results)->firstWhere('ok', true);
            if (!empty($firstOk['local_rel'])) {
                $toy->imagenppal = $firstOk['local_rel'];
                $toy->save();
            }
        }

        return response()->json([
            'ok'      => $okCount > 0,
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
        if (empty($value)) return null;
        if (preg_match('#^https?://#i', $value)) return $value;

        // Normaliza separadores y quita prefijos comunes
        $p = str_replace('\\', '/', $value);
        $p = ltrim($p, '/');
        $p = preg_replace('#^(storage/app/)?public/#i', '', $p); // quita "storage/app/public/" o "public/"

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
        if (!$rel) return null;
        $p = ltrim(str_replace('\\', '/', $rel), '/');
        if (preg_match('#^https?://#i', $p)) return $p;
        if (preg_match('#^storage/#i', $p)) return '/' . $p;
        return Storage::disk('public')->exists($p) ? Storage::disk('public')->url($p) : null;
    }

    private function tryServiceDownloadOne(
        \App\Services\SharePointDownloader $sp,
        string $remotePath,
        string $folderLocal,
        ?string $localName = null,
        ?string $refLabel = null
    ): array {
        // ⚠️ Si tu servicio necesita siteId/driveId y no existen, saltar a fallback
        $siteId = config('sharepoint.site_id', env('SP_SITE_ID'));
        $driveId = config('sharepoint.drive_id', env('SP_DRIVE_ID'));
        if (empty($siteId) || empty($driveId)) {
            return [false, ['ok'=>false,'ref'=>$refLabel,'remote'=>$remotePath,'message'=>'Sin siteId/driveId => usar fallback Graph']];
        }

        $localName = $localName ?: basename($remotePath);
        $destRel = rtrim($folderLocal, '/').'/'.$localName;
        $destAbs = storage_path('app/public/'.$destRel);
        @mkdir(dirname($destAbs), 0775, true);

        try {
            if (method_exists($sp, 'downloadToLocal')) {
                $sp->downloadToLocal($remotePath, $destAbs);
            } elseif (method_exists($sp, 'download')) {
                $bytes = $sp->download($remotePath);
                if (!$bytes) return [false, ['ok'=>false,'ref'=>$refLabel,'remote'=>$remotePath,'message'=>'Servicio devolvió vacío']];
                file_put_contents($destAbs, $bytes);
            } else {
                return [false, ['ok'=>false,'ref'=>$refLabel,'remote'=>$remotePath,'message'=>'Servicio SP sin método download*']];
            }

            return [true, [
                'ok'=>true,'ref'=>$refLabel,'remote'=>$remotePath,'local_rel'=>$destRel,
                'image_url'=>Storage::disk('public')->url($destRel),'source'=>'service',
            ]];
        } catch (\Throwable $e) {
            \Log::warning('tryServiceDownloadOne fallo', ['remote'=>$remotePath,'dest'=>$destAbs,'e'=>$e->getMessage()]);
            return [false, ['ok'=>false,'ref'=>$refLabel,'remote'=>$remotePath,'message'=>$e->getMessage()]];
        }
    }

    private function ensureGraphIds(array $ctx): array
    {
        if (!empty($ctx['driveId']) && !empty($ctx['siteId'])) return $ctx;

        $token = $this->graphToken();
        // Esto te da driveId y rootItemId. El siteId no viene directo; podrías no necesitarlo si tu servicio acepta sólo driveId.
        [$driveId, $rootItemId] = $this->graphGetShareRoot($token, (string)($ctx['shareUrl'] ?? ''));

        $ctx['driveId'] = $driveId;
        // opcional: intenta inferir siteId si tu servicio lo pide; si no, omítelo
        $ctx['siteId']  = $ctx['siteId'] ?? null;

        // Si quieres persistirlos en config/env cache, puedes setearlos aquí (o guardarlos en DB)
        return $ctx;
    }

    /**
     * Fallback completo usando Microsoft Graph (search + crawl), luego descarga el ítem.
     *
     * $it puede tener:
     * - ['remote' => 'Ruta/archivo.ext', 'localName' => '...']  (cuando se especificó explícito)
     * - ['remoteCandidates' => [...], 'localNameCandidates' => [...]]  (cuando generamos candidatos .jpg/.jpeg/.png)
     *
     * @return array{0:bool,1:array}
     */
    private function tryGraphFindAndDownload(
        array $ctx,
        array $it,
        string $folderLocal,
        ?string $refLabel = null
    ): array {
        $token = $this->graphToken();

        // Obtén el root del share (driveId y rootItemId del share)
        [$driveId, $rootItemId] = $this->graphGetShareRoot($token, (string)($ctx['shareUrl'] ?? ''));

        // Arma candidatos de nombre (para search/crawl)
        $candNames   = [];
        $candLocals  = [];

        if (!empty($it['remote'])) {
            $candNames[]  = $it['remote'];
            $candLocals[] = $it['localName'] ?? basename($it['remote']);
        } elseif (!empty($it['remoteCandidates'])) {
            foreach ($it['remoteCandidates'] as $i => $cand) {
                $candNames[] = $cand;
                $candLocals[] = $it['localNameCandidates'][$i] ?? basename($cand);
            }
        }

        // 1) Search directo por cada candidato
        foreach ($candNames as $i => $name) {
            $hit = $this->graphSearchByName($token, $driveId, $rootItemId, $name);
            if ($hit && !empty($hit['id'])) {
                $fileName = $candLocals[$i] ?? ($hit['name'] ?? 'file.bin');

                // Asegura extensión desde el nombre real en Graph
                $ext = pathinfo((string)($hit['name'] ?? ''), PATHINFO_EXTENSION);
                if ($ext && !str_ends_with(strtolower($fileName), '.'.strtolower($ext))) {
                    $fileName .= '.'.$ext;
                }

                $destRel = rtrim($folderLocal, '/').'/'.$fileName;
                $destAbs = storage_path('app/public/'.$destRel);
                @mkdir(dirname($destAbs), 0775, true);

                $this->graphDownloadItemToLocal($token, $driveId, (string)$hit['id'], $destAbs);

                return [true, [
                    'ok'        => true,
                    'ref'       => $refLabel,
                    'query'     => $name,
                    'local_rel' => $destRel,
                    'image_url' => Storage::disk('public')->url($destRel),
                    'source'    => 'graph-search',
                ]];
            }
        }

        // 2) Crawl si el search no encontró
        if (!empty($candNames)) {
            $hit = $this->graphCrawlByName($token, $driveId, $rootItemId, $candNames);
            if ($hit && !empty($hit['id'])) {
                // Usa nombre real encontrado para conservar extensión
                $realName = (string)($hit['name'] ?? 'file.bin');
                $destRel  = rtrim($folderLocal, '/').'/'.$realName;
                $destAbs  = storage_path('app/public/'.$destRel);
                @mkdir(dirname($destAbs), 0775, true);

                $this->graphDownloadItemToLocal($token, $driveId, (string)$hit['id'], $destAbs);

                return [true, [
                    'ok'        => true,
                    'ref'       => $refLabel,
                    'query'     => implode(' | ', $candNames),
                    'local_rel' => $destRel,
                    'image_url' => Storage::disk('public')->url($destRel),
                    'source'    => 'graph-crawl',
                ]];
            }
        }

        return [false, [
            'ok'     => false,
            'ref'    => $refLabel,
            'query'  => $candNames,
            'source' => 'graph',
            'message'=> 'No se encontró el archivo en el share.',
        ]];
    }

    // ---------- Graph/SharePoint utilities (idéntico a tu versión con try/catch) ----------

    private function resolveSpContext(): array
    {
        return [
            'shareUrl' => config('services.msgraph.share_url', env('MSGRAPH_SHARE_URL')),
            'siteId'   => config('sharepoint.site_id', env('SP_SITE_ID')),
            'driveId'  => config('sharepoint.drive_id', env('SP_DRIVE_ID')),
        ];
    }

    private function expandSharePointItems(string $rawPath, array $defaultRefs): array
    {
        $out = [];
        $makeCandidates = function (string $base) {
            $b = trim($base, " \t\n\r\0\x0B/\\");
            return [
                'label'               => pathinfo($b, PATHINFO_FILENAME) ?: $b,
                'remoteCandidates'    => [$b . '.jpg', $b . '.jpeg', $b . '.png'],
                'localNameCandidates' => [$b . '.jpg', $b . '.jpeg', $b . '.png'],
            ];
        };

        if ($rawPath !== '') {
            foreach (explode('+', $rawPath) as $part) {
                $part = trim($part);
                if ($part === '') continue;

                if (preg_match('/\.(jpg|jpeg|png)$/i', $part)) {
                    $out[] = [
                        'label'     => pathinfo($part, PATHINFO_FILENAME),
                        'remote'    => $part,
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
            if ($ref === '') continue;
            $out[] = $makeCandidates($ref);
        }
        return $out;
    }

    private function graphToken(): string
    {
        $tenant = env('MSGRAPH_TENANT_ID');
        $client = env('MSGRAPH_CLIENT_ID');
        $secret = env('MSGRAPH_CLIENT_SECRET');

        $resp = \Illuminate\Support\Facades\Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'client_id'     => $client,
            'client_secret' => $secret,
            'grant_type'    => 'client_credentials',
            'scope'         => 'https://graph.microsoft.com/.default',
        ])->throw();

        return (string) $resp->json('access_token');
    }

    private function graphGetShareRoot(string $token, string $shareUrl): array
    {
        $shareId = $this->graphShareId($shareUrl);
        $url = "https://graph.microsoft.com/v1.0/shares/{$shareId}/driveItem";

        $json = \Illuminate\Support\Facades\Http::withToken($token)->get($url)->throw()->json();

        $driveId  = $json['parentReference']['driveId'] ?? null;
        $itemId   = $json['id'] ?? null;

        if (!$driveId || !$itemId) {
            throw new \RuntimeException('El share no resolvió driveId/itemId.');
        }
        return [$driveId, $itemId];
    }

    private function graphShareId(string $shareUrlOrId): string
    {
        $s = trim($shareUrlOrId, " \t\n\r\0\x0B\"'");
        if (preg_match('#^[us]![A-Za-z0-9\-_]+$#', $s)) return $s;
        return 'u!' . rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    private function graphSearchByName(string $token, string $driveId, string $rootItemId, string $query): ?array
    {
        $q = rawurlencode($query);
        $url = "https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$rootItemId}/search(q='{$q}')";
        try {
            $json = \Illuminate\Support\Facades\Http::withToken($token)->get($url)->throw()->json();
        } catch (\Throwable $e) {
            return null;
        }

        $items = $json['value'] ?? [];
        if (!$items) return null;

        $targets = $this->buildNameMatchers([$query]);

        foreach ($items as $it) {
            if (!isset($it['file'])) continue;
            $name = (string) ($it['name'] ?? '');
            if ($this->nameMatches($name, $targets)) {
                return ['id' => $it['id'], 'name' => $name];
            }
        }
        return null;
    }

    private function graphCrawlByName(string $token, string $driveId, string $rootItemId, array $candNames): ?array
    {
        $queue   = [$rootItemId];
        $visited = [];
        $targets = $this->buildNameMatchers($candNames);
        $maxNodes = 20000;

        while (!empty($queue) && $maxNodes-- > 0) {
            $folderId = array_shift($queue);
            if (isset($visited[$folderId])) continue;
            $visited[$folderId] = true;

            $next = "https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$folderId}/children"
                . "?%24top=200&%24select=id,name,folder,file,parentReference";

            while ($next) {
                $resp = \Illuminate\Support\Facades\Http::withToken($token)->get($next)->throw()->json();
                $items = $resp['value'] ?? [];

                foreach ($items as $it) {
                    $name = (string) ($it['name'] ?? '');
                    if (isset($it['file'])) {
                        if ($this->nameMatches($name, $targets)) {
                            return ['id' => $it['id'], 'name' => $name];
                        }
                    } elseif (isset($it['folder'])) {
                        $queue[] = $it['id'];
                    }
                }

                $next = $resp['@odata.nextLink'] ?? null;
            }
        }

        return null;
    }

    private function buildNameMatchers(array $names): array
    {
        $targets = [];
        foreach ($names as $n) {
            $n = trim($n);
            if ($n === '') continue;

            $base = pathinfo($n, PATHINFO_FILENAME);
            $ext  = strtolower((string) pathinfo($n, PATHINFO_EXTENSION));
            $normFull = $this->normName($n);
            $normBase = $this->normName($base);

            $targets[] = [
                'full' => $normFull,
                'base' => $normBase,
                'exts' => $ext ? [$ext] : ['jpg', 'jpeg', 'png'],
            ];
        }
        return $targets;
    }

    private function normName(string $s): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $s));
    }

    private function nameMatches(string $real, array $targets): bool
    {
        $norm = $this->normName($real);
        $ext  = strtolower((string) pathinfo($real, PATHINFO_EXTENSION));

        foreach ($targets as $t) {
            if ($norm === $t['full']) return true;
            if (in_array($ext, $t['exts'], true) && Str::startsWith($norm, $t['base'])) {
                return true;
            }
        }
        return false;
    }

    private function graphDownloadItemToLocal(string $token, string $driveId, string $itemId, string $destAbs): void
    {
        $url = "https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$itemId}/content";
        \Illuminate\Support\Facades\Http::withToken($token)->sink($destAbs)->get($url)->throw();
    }
}
