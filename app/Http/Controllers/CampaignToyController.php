<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignToy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class CampaignToyController extends Controller
{
    // === Vista Blade (igual) ===
    public function index(Request $request)
    {
        $q = CampaignToy::query()->with('campaign:id,nombre');

        if ($request->filled('idcampaign')) $q->where('idcampaign', (int) $request->input('idcampaign'));
        if ($request->filled('referencia')) $q->where('referencia', 'like', '%' . $request->input('referencia') . '%');
        if ($request->filled('nombre'))     $q->where('nombre',     'like', '%' . $request->input('nombre')     . '%');

        $toys = $q->latest('updated_at')->paginate(15)->withQueryString();

        return view('campaign_toys.index', compact('toys'));
    }

    // === Endpoint para Tabulator (igual) ===
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

        return view('campaigns.toys.edit', compact('campaign', 'toy'));
    }

    public function destroy(\App\Models\Campaign $campaign, \App\Models\CampaignToy $toy)
    {
        if ((int)$toy->idcampaign !== (int)$campaign->id) {
            return response()->json(['ok' => false, 'message' => 'El juguete no pertenece a la campaña.'], 404);
        }

        try {
            // (Opcional) Eliminar imágenes locales relacionadas
            // use Illuminate\Support\Facades\Storage;
            // $folder = "campaign_toys/{$campaign->id}";
            // $refBase = pathinfo((string)$toy->referencia, PATHINFO_FILENAME);
            // if (Storage::disk('public')->exists($folder)) {
            //     foreach (Storage::disk('public')->files($folder) as $f) {
            //         $name = pathinfo($f, PATHINFO_FILENAME);
            //         if (stripos($name, $refBase) === 0) {
            //             Storage::disk('public')->delete($f);
            //         }
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


    private function splitRefs(string $ref): array
    {
        $parts = array_values(array_filter(array_map('trim', explode('+', $ref))));
        return $parts ?: [$ref];
    }

    private function findPublicUrlForRefInFolder(int $campaignId, string $ref): ?string
    {
        $base = "campaign_toys/{$campaignId}/";
        $exts = ['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'];

        foreach ($exts as $ext) {
            $rel = $base . $ref . '.' . $ext;
            if (Storage::disk('public')->exists($rel)) {
                return Storage::disk('public')->url($rel);
            }
        }

        $files = Storage::disk('public')->files($base);
        foreach ($files as $f) {
            $name = pathinfo($f, PATHINFO_FILENAME);
            if (stripos($name, $ref) === 0) {
                return Storage::disk('public')->url($f);
            }
        }

        return null;
    }

    private function toyImagePublicUrl(?string $value, Campaign $campaign): ?string
    {
        if (empty($value)) return null;
        if (preg_match('#^https?://#i', $value)) return $value;

        $p = str_replace('\\', '/', $value);
        $p = ltrim($p, '/');
        $p = preg_replace('#^storage/app/public/#', '', $p);
        $p = preg_replace('#^app/public/#', '', $p);
        $p = preg_replace('#^public/#', '', $p);

        if (preg_match('#^storage/#i', $p)) return '/' . $p;

        if (Storage::disk('public')->exists($p)) {
            return Storage::disk('public')->url($p);
        }

        if (strpos($p, '/') === false) {
            $guess = "campaign_toys/{$campaign->id}/{$p}";
            if (Storage::disk('public')->exists($guess)) {
                return Storage::disk('public')->url($guess);
            }
        }

        return null;
    }

    /**
     * Descarga desde SharePoint a /public/campaign_toys/{campaign}/
     * Si el servicio no encuentra por ruta, caemos a Graph: search + CRAWL (todas las páginas) por nombre.
     * Respuesta compatible con la vista (summary y results con ref/image_url).
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
        $rawPath     = trim((string)$request->input('sp_path', ''));
        $folderLocal = "campaign_toys/{$campaign->id}";
        Storage::disk('public')->makeDirectory($folderLocal);

        // Referencias solicitadas (explícitas o desde la referencia del juguete)
        $defaultRefs = $this->splitRefs((string)$toy->referencia);
        $items       = $this->expandSharePointItems($rawPath, $defaultRefs);

        $results   = [];
        $okCount   = 0;
        $failCount = 0;

        foreach ($items as $it) {
            $refLabel = $it['label'] ?? null;

            // 1) Intento por servicio (descarga por "ruta" interna del drive)
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

            // 2) Fallback robusto: GRAPH (search + CRAWL de todas las páginas)
            [$graphOK, $graphRes] = $this->tryGraphFindAndDownload(
                $ctx,
                $it,
                $folderLocal,
                $refLabel
            );

            $results[] = $graphRes;
            $graphOK ? $okCount++ : $failCount++;
        }

        // Actualiza imagenppal con la primera OK
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

    // ---------- Fallback a Graph: búsqueda + crawl de TODAS las páginas ----------

    private function tryGraphFindAndDownload(array $ctx, array $item, string $folderLocal, ?string $refLabel = null): array
    {
        $token = $this->graphToken();

        $shareUrl = $ctx['shareUrl'] ?? null;
        if (!$shareUrl) {
            return [false, ['ok' => false, 'ref' => $refLabel, 'message' => 'Sin shareUrl configurado en .env (MSGRAPH_SHARE_URL)']];
        }

        try {
            [$driveId, $rootItemId] = $this->graphGetShareRoot($token, $shareUrl);
        } catch (\Throwable $e) {
            return [false, ['ok' => false, 'ref' => $refLabel, 'message' => 'No se pudo resolver el share: ' . $e->getMessage()]];
        }

        // Candidatos de nombre (exactos y normalizados)
        $candNames = [];
        if (!empty($item['remote'])) {
            $candNames[] = basename($item['remote']);
        } elseif (!empty($item['localName'])) {
            $candNames[] = basename($item['localName']);
        } elseif (!empty($item['remoteCandidates'])) {
            foreach ($item['remoteCandidates'] as $rc) $candNames[] = basename($rc);
        }
        // Asegurar variantes sin extensión
        foreach ($candNames as $n) {
            $base = pathinfo($n, PATHINFO_FILENAME);
            if (!in_array($base, $candNames, true)) $candNames[] = $base;
        }
        $candNames = array_values(array_unique($candNames));

        // Intento 1: /search
        $found = null;
        foreach ($candNames as $q) {
            $found = $this->graphSearchByName($token, $driveId, $rootItemId, $q);
            if ($found) break;
        }

        // Intento 2: CRAWL total
        if (!$found) {
            $found = $this->graphCrawlByName($token, $driveId, $rootItemId, $candNames);
            if (!$found) {
                return [false, ['ok' => false, 'ref' => $refLabel, 'message' => 'Sin resultados en búsqueda/crawl Graph']];
            }
        }

        // Descargar por itemId
        try {
            $localName = $found['name'] ?? ($candNames[0] ?? 'file.jpg');
            $localRel  = $folderLocal . '/' . $localName;
            $localAbs  = Storage::disk('public')->path($localRel);

            $this->graphDownloadItemToLocal($token, $driveId, $found['id'], $localAbs);

            return [true, [
                'ok'        => true,
                'ref'       => $refLabel,
                'remote'    => $found['id'],
                'local_rel' => $localRel,
                'local_url' => Storage::disk('public')->url($localRel),
                'image_url' => Storage::disk('public')->url($localRel),
                'via'       => 'graph',
            ]];
        } catch (\Throwable $e) {
            return [false, ['ok' => false, 'ref' => $refLabel, 'message' => 'Error al descargar por Graph: ' . $e->getMessage()]];
        }
    }

    private function graphToken(): string
    {
        $tenant = env('MSGRAPH_TENANT_ID');
        $client = env('MSGRAPH_CLIENT_ID');
        $secret = env('MSGRAPH_CLIENT_SECRET');

        $resp = Http::asForm()->post("https://login.microsoftonline.com/{$tenant}/oauth2/v2.0/token", [
            'client_id'     => $client,
            'client_secret' => $secret,
            'grant_type'    => 'client_credentials',
            'scope'         => 'https://graph.microsoft.com/.default',
        ])->throw();

        return (string) $resp->json('access_token');
    }

    /** Devuelve [driveId, itemIdRaiz] desde un share link. */
    private function graphGetShareRoot(string $token, string $shareUrl): array
    {
        $shareId = $this->graphShareId($shareUrl);
        $url = "https://graph.microsoft.com/v1.0/shares/{$shareId}/driveItem";

        $json = Http::withToken($token)->get($url)->throw()->json();

        $driveId  = $json['parentReference']['driveId'] ?? null;
        $itemId   = $json['id'] ?? null;

        if (!$driveId || !$itemId) {
            throw new \RuntimeException('El share no resolvió driveId/itemId.');
        }
        return [$driveId, $itemId];
    }

    /** shareId correcto para /shares/{shareId}: "u!" + base64url(sharingUrl) */
    private function graphShareId(string $shareUrlOrId): string
    {
        $s = trim($shareUrlOrId, " \t\n\r\0\x0B\"'");
        if (preg_match('#^[us]![A-Za-z0-9\-_]+$#', $s)) return $s;
        return 'u!' . rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    /** Busca por nombre con /search y filtra localmente por igualdad/empieza-con (normalizado). */
    private function graphSearchByName(string $token, string $driveId, string $rootItemId, string $query): ?array
    {
        $q = rawurlencode($query);
        $url = "https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$rootItemId}/search(q='{$q}')";
        try {
            $json = Http::withToken($token)->get($url)->throw()->json();
        } catch (\Throwable $e) {
            return null; // dejamos que el crawl haga el trabajo
        }

        $items = $json['value'] ?? [];
        if (!$items) return null;

        $targets = $this->buildNameMatchers([$query]);

        foreach ($items as $it) {
            if (!isset($it['file'])) continue; // omite carpetas
            $name = (string) ($it['name'] ?? '');
            if ($this->nameMatches($name, $targets)) {
                return ['id' => $it['id'], 'name' => $name];
            }
        }
        return null;
    }

    /** Recorre TODAS las páginas y subcarpetas (BFS) comparando nombres de archivo. */
    private function graphCrawlByName(string $token, string $driveId, string $rootItemId, array $candNames): ?array
    {
        $queue   = [$rootItemId];
        $visited = [];
        $targets = $this->buildNameMatchers($candNames);
        $maxNodes = 20000; // límite de seguridad

        while (!empty($queue) && $maxNodes-- > 0) {
            $folderId = array_shift($queue);
            if (isset($visited[$folderId])) continue;
            $visited[$folderId] = true;

            $next = "https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$folderId}/children"
                . "?%24top=200&%24select=id,name,folder,file,parentReference";

            while ($next) {
                $resp = Http::withToken($token)->get($next)->throw()->json();
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

    /** Construye comparadores normalizados (igual y empieza-con) para varios candidatos. */
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

    /** Normaliza nombre: minúsculas + sin caracteres no alfanuméricos. */
    private function normName(string $s): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]/', '', $s));
    }

    /** ¿Coincide el nombre real con alguno de los targets (exacto o empieza-con)? */
    private function nameMatches(string $real, array $targets): bool
    {
        $norm = $this->normName($real);
        $ext  = strtolower((string) pathinfo($real, PATHINFO_EXTENSION));

        foreach ($targets as $t) {
            if ($norm === $t['full']) return true; // match exacto
            if (in_array($ext, $t['exts'], true) && Str::startsWith($norm, $t['base'])) {
                return true;
            }
        }
        return false;
    }

    /** Descarga el contenido de un itemId a disco. */
    private function graphDownloadItemToLocal(string $token, string $driveId, string $itemId, string $destAbs): void
    {
        $url = "https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$itemId}/content";
        Http::withToken($token)->sink($destAbs)->get($url)->throw();
    }

    // ---------- Descarga por el servicio existente (si sabe resolver rutas) ----------

    private function tryServiceDownloadOne($sp, string $driveRel, string $folderLocal, string $localName, ?string $refLabel = null): array
    {
        $localRel = $folderLocal . '/' . basename($localName);
        $localAbs = Storage::disk('public')->path($localRel);

        try {
            $sp->downloadToLocal($driveRel, $localAbs);
            $public = Storage::disk('public')->url($localRel);

            return [true, [
                'ok'        => true,
                'ref'       => $refLabel,
                'remote'    => $driveRel,
                'local_rel' => $localRel,
                'local_url' => $public,
                'image_url' => $public,
                'via'       => 'service',
            ]];
        } catch (\Throwable $e) {
            return [false, [
                'ok'      => false,
                'ref'     => $refLabel,
                'remote'  => $driveRel,
                'message' => 'Servicio no pudo descargar: ' . $e->getMessage(),
            ]];
        }
    }

    // ---------- Utilidades varias ----------

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

    private function publicUrlIfExists(?string $rel): ?string
    {
        if (!$rel) return null;
        $p = ltrim(str_replace('\\', '/', $rel), '/');
        if (preg_match('#^https?://#i', $p)) return $p;
        if (preg_match('#^storage/#i', $p)) return '/' . $p;
        return Storage::disk('public')->exists($p) ? Storage::disk('public')->url($p) : null;
    }
}
