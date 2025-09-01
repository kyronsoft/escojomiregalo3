<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\CampaignToy;
use Illuminate\Http\Request;

class CampaignToysController extends Controller
{
    public function index(Campaign $campaign)
    {
        // Solo muestra la vista; Tabulator hará ajax a .data
        return view('campaigns.toys', [
            'campaign' => $campaign,
        ]);
    }

    public function data(Campaign $campaign)
    {
        // Referencias asignadas a la campaña
        $rows = CampaignToy::query()
            ->where('idcampaign', $campaign->id)
            ->select([
                'id',
                'referencia',
                'nombre',
                'imagenppal',
                'genero',
                'desde',
                'hasta',
                'unidades',
                'porcentaje',
                'imgexists',
                'escogidos',
                'updated_at',
            ])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json($rows);
    }
}
