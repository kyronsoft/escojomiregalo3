<?php

namespace App\Http\Controllers;

use App\Exports\CampaignToysExport;
use App\Models\Campaign;
use App\Models\CampaignToy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class CampaignToysController extends Controller
{
    /** Muestra la vista de juguetes de una campaña */
    public function index(Campaign $campaign)
    {
        return view('campaigns.toys', ['campaign' => $campaign]);
    }

    /** Devuelve los juguetes (JSON) de la campaña, con URLs de imagen resueltas */
    public function data(Request $request, Campaign $campaign)
    {
        $q = CampaignToy::query()
            ->where('idcampaign', $campaign->id)
            ->latest('updated_at');

        if ($request->filled('referencia')) {
            $q->where('referencia', 'like', '%' . $request->input('referencia') . '%');
        }
        if ($request->filled('nombre')) {
            $q->where('nombre', 'like', '%' . $request->input('nombre') . '%');
        }

        $rows = $q->get([
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
            [$urls, $partsCnt] = $this->buildImageData($t); // ← NUEVO

            return [
                'id'                 => $t->id,
                'idcampaign'         => $t->idcampaign,
                'referencia'         => $t->referencia,
                'nombre'             => $t->nombre,
                'imagenppal'         => $t->imagenppal,
                'image_url'          => $urls[0] ?? null, // compat anterior (primera)
                'image_urls'         => $urls,            // ← hasta 2 URLs para combos de 2
                'image_parts_count'  => $partsCnt,        // ← total de partes en combo
                'genero'             => $t->genero,
                'unidades'           => $t->unidades,
                'precio_unitario'    => $t->precio_unitario,
                'porcentaje'         => $t->porcentaje,
                'updated_at'         => $t->updated_at,
            ];
        });

        return response()->json($out);
    }

    /** Construye hasta 2 URLs públicas y retorna [urls[], partsCount] */
    private function buildImageData(CampaignToy $toy, int $max = 2): array
    {
        $raw = trim((string) $toy->imagenppal);
        if ($raw === '') {
            return [[], 0];
        }

        $parts = collect(explode('+', $raw))
            ->map(fn($v) => trim($v))
            ->filter()
            ->values();

        $partsCount = $parts->count();
        if ($partsCount === 0) {
            return [[], 0];
        }

        // Tomamos hasta $max partes para devolver sus URLs (para combos de 2, ambas)
        $firstParts = $parts->take($max)->all();

        $urls = [];
        foreach ($firstParts as $first) {
            $urls[] = $this->resolvePartUrl($toy->idcampaign, $first);
        }

        return [$urls, $partsCount];
    }

    /** Actualiza únicamente las unidades de un toy via AJAX */
    public function updateUnidades(Request $request, Campaign $campaign, CampaignToy $toy)
    {
        if ((int) $toy->idcampaign !== (int) $campaign->id) {
            abort(404);
        }

        $validated = $request->validate([
            'unidades' => ['required', 'integer', 'min:0'],
        ]);

        $toy->unidades = $validated['unidades'];
        $toy->save();

        return response()->json([
            'ok'       => true,
            'unidades' => $toy->unidades,
        ]);
    }

    /** Descarga Excel con todos los juguetes de la campaña */
    public function export(Campaign $campaign)
    {
        $filename = 'referencias_campana_' . $campaign->id . '_' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new CampaignToysExport($campaign->id), $filename);
    }

    /** Resuelve la URL pública de una parte (archivo) */
    private function resolvePartUrl(int $campaignId, string $file): ?string
    {
        if ($file === '') return null;

        // URL absoluta (http/https)
        if (Str::startsWith($file, ['http://', 'https://'])) {
            return $file;
        }

        // Normalizar path relativo
        if (Str::startsWith($file, '/')) {
            $file = ltrim($file, '/');
        }

        $path = Str::startsWith($file, 'campaign_toys/')
            ? $file
            : "campaign_toys/{$campaignId}/{$file}";

        if (!Storage::disk('public')->exists($path)) {
            return null; // el front hará fallback a placeholder
        }

        return Storage::disk('public')->url($path);
    }
}
