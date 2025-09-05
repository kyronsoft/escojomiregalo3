<?php

namespace App\Exports;

use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;

class SeleccionadosExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize
{
    use Exportable;

    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $campaignId = $this->filters['idcampaign'] ?? ($this->filters['idcampaing'] ?? null);
        $referencia = $this->filters['referencia'] ?? null;
        $documento  = $this->filters['documento'] ?? null;
        $dateFrom   = $this->filters['date_from'] ?? null;
        $dateTo     = $this->filters['date_to'] ?? null;

        $q = DB::table('seleccionados as s')
            ->leftJoin('campaigns as c', 'c.id', '=', 's.idcampaing')
            ->leftJoin('campaign_toys as t', function ($join) {
                $join->on('t.idcampaign', '=', 's.idcampaing')
                    ->on('t.referencia', '=', 's.referencia');
            })
            ->select([
                's.id',
                's.idcampaing',
                's.referencia',
                's.documento',
                's.created_at',
                DB::raw("COALESCE(NULLIF(t.nombre,''), CONCAT('Ref ', s.referencia)) as toy_name"),
                'c.nombre as campaign_name',
            ]);

        if (!empty($campaignId)) {
            $q->where('s.idcampaing', (int) $campaignId);
        }
        if (!empty($referencia)) {
            $q->where('s.referencia', 'like', "%{$referencia}%");
        }
        if (!empty($documento)) {
            $q->where('s.documento', 'like', "%{$documento}%");
        }
        if (!empty($dateFrom)) {
            $q->whereDate('s.created_at', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $q->whereDate('s.created_at', '<=', $dateTo);
        }

        return $q->orderByDesc('s.created_at');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Campaña',
            'ID Campaña',
            'Referencia',
            'Nombre Juguete',
            'Documento',
            'Fecha selección',
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->campaign_name ?? ('#' . $row->idcampaing),
            $row->idcampaing,
            $row->referencia,
            $row->toy_name,
            $row->documento,
            optional($row->created_at)->format('Y-m-d H:i:s') ?? (string)$row->created_at,
        ];
    }
}
