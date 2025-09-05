<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignToy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use App\Services\SharePointDownloader;
use Illuminate\Support\Facades\Storage;

class CampaignToyController extends Controller
{
    // === Vista Blade (como ya la tienes) ===
    public function index(Request $request)
    {
        $q = CampaignToy::query()->with('campaign:id,nombre');

        if ($request->filled('idcampaign')) {
            $q->where('idcampaign', (int) $request->input('idcampaign'));
        }
        if ($request->filled('referencia')) {
            $q->where('referencia', 'like', '%' . $request->input('referencia') . '%');
        }
        if ($request->filled('nombre')) {
            $q->where('nombre', 'like', '%' . $request->input('nombre') . '%');
        }

        $toys = $q->latest('updated_at')->paginate(15)->withQueryString();

        return view('campaign_toys.index', compact('toys'));
    }

    // === Endpoint para Tabulator ===
    public function data(Request $request)
    {
        $q = CampaignToy::query()->with('campaign:id,nombre');

        if ($request->filled('idcampaign')) {
            $q->where('idcampaign', (int) $request->input('idcampaign'));
        }
        if ($request->filled('referencia')) {
            $q->where('referencia', 'like', '%' . $request->input('referencia') . '%');
        }
        if ($request->filled('nombre')) {
            $q->where('nombre', 'like', '%' . $request->input('nombre') . '%');
        }

        // Devuelve todo para Tabulator (si te preocupa el volumen, pon un limit o server-side pagination)
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

        // Gracias a $appends en el modelo, image_url e image_parts_count ya vienen,
        // pero por seguridad mapeamos explícitamente lo que el front necesita.
        $out = $rows->map(function (CampaignToy $t) {
            return [
                'id'                 => $t->id,
                'idcampaign'         => $t->idcampaign,
                'referencia'         => $t->referencia,
                'nombre'             => $t->nombre,
                'imagenppal'         => $t->imagenppal,
                'image_url'          => $t->image_url,          // <-- listo para usar en <img src="...">
                'image_parts_count'  => $t->image_parts_count,  // <-- para badge +n
                'genero'             => $t->genero,
                'unidades'           => $t->unidades,
                'precio_unitario'    => $t->precio_unitario,
                'porcentaje'         => $t->porcentaje,
                'updated_at'         => $t->updated_at,
                // opcionalmente también campaign nombre:
                'campaign_nombre'    => optional($t->campaign)->nombre,
            ];
        });

        return response()->json($out);
    }

    public function edit(Campaign $campaign, CampaignToy $toy)
    {
        if ((int)$toy->idcampaign !== (int)$campaign->id) abort(404);

        $parts = $this->splitRefs((string) $toy->referencia);

        // Mapa ref => URL pública (o null)
        $imageMap = [];
        foreach ($parts as $ref) {
            $imageMap[$ref] = $this->findPublicUrlForRefInFolder($campaign->id, $ref);
        }

        // Compatibilidad: URL individual (si la tuvieras guardada)
        $toy->image_map = $imageMap;
        $toy->image_url = $this->toyImagePublicUrl($toy->imagenppal, $campaign);

        return view('campaigns.toys.edit', compact('campaign', 'toy'));
    }

    private function splitRefs(string $ref): array
    {
        $parts = array_values(array_filter(array_map('trim', explode('+', $ref))));
        return $parts ?: [$ref];
    }

    /**
     * Busca URL pública en public/campaign_toys/{campaignId}/ para una referencia.
     * - prueba ref.ext (jpg/jpeg/png/JPG/JPEG/PNG)
     * - si no, busca archivos cuyo nombre base comience por ref (p.ej. ref-1.jpg)
     */
    private function findPublicUrlForRefInFolder(int $campaignId, string $ref): ?string
    {
        $base = "campaign_toys/{$campaignId}/";
        $exts = ['jpg', 'jpeg', 'png', 'JPG', 'JPEG', 'PNG'];

        // 1) coincidencia exacta: ref.ext
        foreach ($exts as $ext) {
            $rel = $base . $ref . '.' . $ext;
            if (Storage::disk('public')->exists($rel)) {
                return Storage::disk('public')->url($rel);
            }
        }

        // 2) prefijo: ref* (primer match)
        $files = Storage::disk('public')->files($base);
        foreach ($files as $f) {
            $name = pathinfo($f, PATHINFO_FILENAME);
            if (stripos($name, $ref) === 0) {
                return Storage::disk('public')->url($f);
            }
        }

        return null;
    }

    /**
     * Normaliza rutas varias a URL pública si existen en el disco 'public'.
     */
    private function toyImagePublicUrl(?string $value, Campaign $campaign): ?string
    {
        if (empty($value)) return null;

        // Absoluta
        if (preg_match('#^https?://#i', $value)) return $value;

        // Normaliza separadores/prefijos
        $p = str_replace('\\', '/', $value);
        $p = ltrim($p, '/');
        $p = preg_replace('#^storage/app/public/#', '', $p);
        $p = preg_replace('#^app/public/#', '', $p);
        $p = preg_replace('#^public/#', '', $p);

        // Si ya viene como "storage/..."
        if (preg_match('#^storage/#i', $p)) return '/' . $p;

        // Si existe tal cual en el disco
        if (Storage::disk('public')->exists($p)) {
            return Storage::disk('public')->url($p);
        }

        // Si es solo nombre de archivo, prueba la carpeta real: campaign_toys/{id}/
        if (strpos($p, '/') === false) {
            $guess = "campaign_toys/{$campaign->id}/{$p}";
            if (Storage::disk('public')->exists($guess)) {
                return Storage::disk('public')->url($guess);
            }
        }

        return null;
    }


    public function update(Request $request, Campaign $campaign, CampaignToy $toy)
    {
        if ($toy->idcampaign !== $campaign->id) {
            abort(404);
        }

        $data = $request->validate([
            'referencia'      => ['required', 'string', 'max:100'],
            'nombre'          => ['required', 'string'],
            'genero'          => ['nullable', 'in:NIÑO,NIÑA,UNISEX'],
            'unidades'        => ['nullable', 'integer', 'min:0'],
            'precio_unitario' => ['nullable', 'integer', 'min:0'],
            'porcentaje'      => ['nullable', 'string', 'max:100'],
            'descripcion'     => ['nullable', 'string'],
        ]);

        $toy->update($data);

        return redirect()
            ->route('campaigns.toys.edit', [$campaign->id, $toy->id])
            ->with('success', 'Juguete actualizado correctamente.');
    }

    public function fetchImageFromSharePoint(Request $request, Campaign $campaign, CampaignToy $toy, SharePointDownloader $sp)
    {
        if ((int)$toy->idcampaign !== (int)$campaign->id) {
            return response()->json(['ok' => false, 'message' => 'Toy no pertenece a la campaña'], 404);
        }

        $request->validate(['sp_path' => ['nullable', 'string', 'max:1000']]);

        // Refs del input (p.ej. "ABC+DEF") o de la referencia del juguete
        $raw  = trim((string)$request->input('sp_path', ''));
        $refs = $raw !== ''
            ? array_values(array_filter(array_map(fn($s) => trim(pathinfo($s, PATHINFO_FILENAME)), explode('+', $raw))))
            : $this->splitRefs((string)$toy->referencia);

        if (empty($refs)) $refs = [(string)$toy->referencia];

        // ← carpeta CORRECTA en tu storage público
        $folder = 'campaign_toys/' . $campaign->id;

        $results = [];
        $totalOk = 0;
        $totalFail = 0;

        foreach ($refs as $ref) {
            // ¿el usuario puso extensión explícita? respétala para ese ref
            if ($raw !== '' && preg_match('/(^|[+])' . preg_quote($ref, '/') . '\.(jpg|jpeg|png)($|[+])/i', $raw, $m)) {
                $candidates = [$ref . '.' . strtolower($m[2])];
            } else {
                $candidates = [$ref . '.jpg', $ref . '.jpeg', $ref . '.png'];
            }

            $done = false;
            $lastErr = null;
            $used = null;
            $url = null;

            foreach ($candidates as $cand) {
                try {
                    $destRel = $folder . '/' . basename($cand);           // public/campaign_toys/{id}/ref.ext
                    $destAbs = Storage::disk('public')->path($destRel);

                    $sp->downloadToLocal($cand, $destAbs);            // raíz del drive
                    $url  = Storage::disk('public')->url($destRel);
                    $used = $cand;
                    $done = true;
                    break;
                } catch (\Throwable $e) {
                    $lastErr = $e;
                }
            }

            if ($done) {
                $totalOk++;
                $results[] = ['ref' => $ref, 'ok' => true,  'image_url' => $url, 'source' => $used];
            } else {
                $totalFail++;
                $results[] = ['ref' => $ref, 'ok' => false, 'image_url' => null, 'source' => null, 'message' => $lastErr?->getMessage()];
            }
        }

        // Actualiza imagenppal con la primera OK (opcional)
        if ($totalOk > 0) {
            $firstOk = collect($results)->firstWhere('ok', true);
            if ($firstOk) {
                $toy->imagenppal = $folder . '/' . basename((string)$firstOk['source']);
                $toy->save();
            }
        }

        return response()->json([
            'ok'      => $totalOk > 0,
            'summary' => ['ok' => $totalOk, 'fail' => $totalFail, 'total' => count($results)],
            'results' => $results,
        ]);
    }

    private function guessPublicUrlForRef(Campaign $campaign, string $ref): ?string
    {
        foreach (['jpg', 'jpeg', 'png'] as $ext) {
            $rel = 'campaigns/' . $campaign->id . '/' . $ref . '.' . $ext;
            if (Storage::disk('public')->exists($rel)) {
                return Storage::disk('public')->url($rel);
            }
        }
        return null;
    }
}
