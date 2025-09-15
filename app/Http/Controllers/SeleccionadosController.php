<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

// Para exportar a Excel (xlsx)
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SeleccionadosController extends Controller
{
    /**
     * Vista con filtros (Tabulator se alimenta desde data()).
     */
    public function index(Request $request)
    {
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(5, min($perPage, 200));

        $campaigns = Campaign::orderBy('updated_at', 'desc')->get(['id', 'nombre']);

        // mantener valores en inputs
        $campaignId = $request->input('idcampaign', $request->input('idcampaing'));
        $referencia = $request->input('referencia');
        $documento  = $request->input('documento');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');

        return view('seleccionados.index', compact(
            'campaigns',
            'campaignId',
            'referencia',
            'documento',
            'dateFrom',
            'dateTo',
            'perPage'
        ));
    }

    /**
     * Endpoint JSON para Tabulator (paginación remota).
     */
    public function data(Request $request)
    {
        $size = (int) ($request->input('size', $request->input('per_page', 25)));
        $size = max(5, min($size, 200));

        $page = (int) $request->input('page', 1);
        $page = max(1, $page);

        $q = $this->baseQuery($request);

        // Ordenamiento remoto opcional
        $allowedSorts = [
            'referencia'   => 's.referencia',
            'created_at'   => 's.created_at',
            'documento'    => 'cc.documento', // ← ahora viene de la pivote
            'colaborador'  => 'col.nombre',
            'telefono'     => 'col.telefono',
            'nombre_hijo'  => 'h.nombre_hijo',
            'genero'       => 'h.genero',
            'rango_edad'   => 'h.rango_edad',
            'direccion'    => 'col.direccion',
            'indicaciones' => 'col.observaciones',
            'ciudad'       => 'col.ciudad',
            'departamento' => 'ciu.departamento',
            'sucursal'     => 'col.sucursal',
            'email'        => 'col.email',
            'empresa'      => DB::raw('COALESCE(e.nombre, c.nombre)'),
            'selected'     => DB::raw('CASE WHEN s.id IS NULL THEN 0 ELSE 1 END'), // opcional
        ];

        $sorters = $request->input('sorters');
        if (is_string($sorters)) {
            $dec = json_decode($sorters, true);
            if (json_last_error() === JSON_ERROR_NONE) $sorters = $dec;
        }
        if (is_array($sorters) && count($sorters) > 0) {
            foreach ($sorters as $srt) {
                $field = $srt['field'] ?? null;
                $dir   = strtolower($srt['dir'] ?? 'asc');
                if ($field && isset($allowedSorts[$field])) {
                    $dir = in_array($dir, ['asc', 'desc'], true) ? $dir : 'asc';
                    $q->orderBy($allowedSorts[$field], $dir);
                }
            }
        } else {
            // Por defecto: seleccionados recientes arriba; luego por nombre para los no seleccionados
            $q->orderBy(DB::raw('CASE WHEN s.created_at IS NULL THEN 1 ELSE 0 END')) // seleccionados primero
                ->orderByDesc('s.created_at')
                ->orderBy('col.nombre');
        }

        $paginator = $q->paginate($size, ['*'], 'page', $page);

        return response()->json(
            $paginator,
            200,
            ['Content-Type' => 'application/json; charset=UTF-8'],
            JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Exportación XLSX con las columnas exactas y en el orden solicitado.
     */
    public function export(Request $request)
    {
        // Reusamos los filtros del request sobre la misma baseQuery, sin paginar.
        // Orden por seleccionados/fecha como en data()
        $q = $this->baseQuery($request)
            ->orderBy(DB::raw('CASE WHEN s.created_at IS NULL THEN 1 ELSE 0 END'))
            ->orderByDesc('s.created_at')
            ->orderBy('col.nombre');

        $rows = $q->get();

        // Mapeo al orden EXACTO requerido
        $exportRows = $rows->map(function ($r) {
            $fecha = null;
            if (!empty($r->created_at)) {
                try {
                    $fecha = Carbon::parse($r->created_at)->format('Y-m-d H:i');
                } catch (\Throwable $e) {
                    $fecha = (string) $r->created_at;
                }
            }
            return [
                $r->referencia,                          // Referencia
                $fecha,                                   // Fecha Selección
                $r->documento,                            // Documento
                $r->colaborador,                          // Colaborador
                $r->telefono,                             // Telefono
                $r->nombre_hijo,                          // Nombre Hijo
                $r->genero_hijo,                          // Genero
                $r->rango_edad,                           // Rango Edad
                $r->direccion,                            // Dirección
                $r->indicaciones,                         // Indicaciones
                $r->ciudad,                               // Codigo Ciudad
                $r->departamento,                         // Departamento
                $r->sucursal,                             // Sucursal
                $r->email,                                // Email
                $r->empresa,                              // Empresa
            ];
        });

        $headings = [
            'Referencia',
            'Fecha Selección',
            'Documento',
            'Colaborador',
            'Telefono',
            'Nombre Hijo',
            'Genero',
            'Rango Edad',
            'Dirección',
            'Indicaciones',
            'Codigo Ciudad',
            'Departamento',
            'Sucursal',
            'Email',
            'Empresa',
        ];

        $export = new class($exportRows, $headings) implements FromCollection, WithHeadings {
            private $rows;
            private $headings;

            public function __construct($rows, $headings)
            {
                $this->rows = $rows;
                $this->headings = $headings;
            }

            public function collection()
            {
                return $this->rows;
            }

            public function headings(): array
            {
                return $this->headings;
            }
        };

        $filename = 'seleccionados_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download($export, $filename);
    }

    /**
     * Builder base con filtros compartidos (index/export/data).
     * Ahora parte de la ASIGNACIÓN de colaboradores (cc) y LEFT JOIN a seleccionados (s),
     * para incluir también los NO seleccionados (s.* = NULL).
     */
    private function baseQuery(Request $request)
    {
        $campaignId = $request->input('idcampaign', $request->input('idcampaing'));
        $referencia = $request->input('referencia');
        $documento  = $request->input('documento');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');

        $q = DB::table('campaing_colaboradores as cc')
            // colaboradores asignados a la campaña
            ->leftJoin('colaboradores as col', 'col.documento', '=', 'cc.documento')
            // seleccionados (si existe selección); importantísimo el join por id de campaña
            ->leftJoin('seleccionados as s', function ($join) {
                $join->on('s.documento', '=', 'cc.documento')
                    ->on('s.idcampaing', '=', 'cc.idcampaign');
            })
            // otros joins igual que antes pero ahora referenciando cc/s
            ->leftJoin('colaborador_hijos as h', 'h.id', '=', 's.idhijo')
            ->leftJoin('campaigns as c', 'c.id', '=', 'cc.idcampaign')
            ->leftJoin('campaign_toys as t', function ($join) {
                $join->on('t.idcampaign', '=', 'cc.idcampaign')
                    ->on('t.referencia',  '=', 's.referencia');
            })
            ->leftJoin('ciudades as ciu', 'ciu.codigo', '=', 'col.ciudad')
            ->leftJoin('empresas as e', 'e.nit', '=', 'col.nit')
            ->select([
                's.id',
                DB::raw('cc.idcampaign as idcampaing'), // homogeneizamos el nombre usado en vistas previas

                // ===== columnas requeridas =====
                's.referencia',
                's.created_at',
                DB::raw('cc.documento as documento'),
                'col.nombre as colaborador',
                'col.telefono',
                'h.nombre_hijo',
                'h.genero as genero_hijo',
                'h.rango_edad',
                'col.direccion',
                DB::raw("COALESCE(NULLIF(col.observaciones,''), '') as indicaciones"),
                'col.ciudad',
                'ciu.departamento',
                'col.sucursal',
                'col.email',
                DB::raw('COALESCE(NULLIF(e.nombre,""), c.nombre) as empresa'),

                // extras opcionales
                DB::raw("COALESCE(NULLIF(t.nombre,''), CASE WHEN s.referencia IS NOT NULL THEN CONCAT('Ref ', s.referencia) ELSE '' END) as toy_name"),
                'c.nombre as campaign_name',

                // útil para UI: flag de seleccionado vs. no seleccionado
                DB::raw('CASE WHEN s.id IS NULL THEN 0 ELSE 1 END as selected'),
            ]);

        // ===== Filtros =====
        if (!empty($campaignId)) {
            $q->where('cc.idcampaign', (int) $campaignId);
        }

        if (!empty($referencia)) {
            // Solo aplica a los que tienen selección; los que no, permanecen (si quieres excluirlos, usa whereNotNull)
            $ref = str_replace('%', '\%', $referencia);
            $q->where(function ($w) use ($ref) {
                $w->where('s.referencia', 'like', '%' . $ref . '%');
            });
        }

        if (!empty($documento)) {
            $doc = str_replace('%', '\%', $documento);
            $q->where('cc.documento', 'like', '%' . $doc . '%');
        }

        // Rango de fechas: aplica sobre s.created_at (los NO seleccionados no filtran por fecha)
        if (!empty($dateFrom)) {
            $q->where(function ($w) use ($dateFrom) {
                $w->whereDate('s.created_at', '>=', $dateFrom)
                    ->orWhereNull('s.created_at'); // ← conserva no seleccionados
            });
        }
        if (!empty($dateTo)) {
            $q->where(function ($w) use ($dateTo) {
                $w->whereDate('s.created_at', '<=', $dateTo)
                    ->orWhereNull('s.created_at'); // ← conserva no seleccionados
            });
        }

        return $q;
    }
}
