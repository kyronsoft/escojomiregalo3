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

        return view('campaigns.toys.edit', compact('campaign', 'toy'));
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
        $rawPath     = trim((string)$request->input('sp_path', ''));
        $folderLocal = "campaign_toys/{$campaign->id}";
        Storage::disk('public')->makeDirectory($folderLocal);

        $defaultRefs = $this->splitRefs((string)$toy->referencia);
        $items       = $this->expandSharePointItems($rawPath, $defaultRefs);

        $results   = [];
        $okCount   = 0;
        $failCount = 0;

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

    private function publicUrlIfExists(?string $rel): ?string
    {
        if (!$rel) return null;
        $p = ltrim(str_replace('\\', '/', $rel), '/');
        if (preg_match('#^https?://#i', $p)) return $p;
        if (preg_match('#^storage/#i', $p)) return '/' . $p;
        return Storage::disk('public')->exists($p) ? Storage::disk('public')->url($p) : null;
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
