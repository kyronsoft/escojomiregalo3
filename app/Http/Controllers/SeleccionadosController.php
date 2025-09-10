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
            'documento'    => 's.documento',
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
            $q->orderByDesc('s.created_at');
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
        $q = $this->baseQuery($request)->orderByDesc('s.created_at');

        // Obtenemos la colección (sin paginate)
        $rows = $q->get();

        // Mapeamos al orden EXACTO requerido por el usuario:
        // Referencia, Fecha Selección, Documento, Colaborador, Telefono, Nombre Hijo,
        // Genero, Rango Edad, Dirección, Indicaciones, Codigo Ciudad, Departamento,
        // Sucursal, Email, Empresa
        $exportRows = $rows->map(function ($r) {
            // Normaliza/da formato a fecha
            $fecha = null;
            if (!empty($r->created_at)) {
                try {
                    $fecha = Carbon::parse($r->created_at)->format('Y-m-d H:i');
                } catch (\Throwable $e) {
                    $fecha = (string) $r->created_at;
                }
            }

            return [
                $r->referencia,
                $fecha,
                $r->documento,
                $r->colaborador,
                $r->telefono,
                $r->nombre_hijo,
                $r->genero_hijo,
                $r->rango_edad,
                $r->direccion,
                $r->indicaciones,
                $r->ciudad,
                $r->departamento,
                $r->sucursal,
                $r->email,
                $r->empresa,
            ];
        });

        // Encabezados EXACTOS y en el MISMO orden
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

        // Clase anónima para exportar sin crear un archivo de export dedicado
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
     * Devuelve las columnas necesarias para Tabulator y para exportación.
     */
    private function baseQuery(Request $request)
    {
        $campaignId = $request->input('idcampaign', $request->input('idcampaing'));
        $referencia = $request->input('referencia');
        $documento  = $request->input('documento');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');

        $q = DB::table('seleccionados as s')
            ->leftJoin('campaigns as c', 'c.id', '=', 's.idcampaing')
            ->leftJoin('campaign_toys as t', function ($join) {
                $join->on('t.idcampaign', '=', 's.idcampaing')
                    ->on('t.referencia',  '=', 's.referencia');
            })
            ->leftJoin('colaborador_hijos as h', 'h.id', '=', 's.idhijo')
            ->leftJoin('colaboradores as col', 'col.documento', '=', 's.documento')
            ->leftJoin('ciudades as ciu', 'ciu.codigo', '=', 'col.ciudad')
            ->leftJoin('empresas as e', 'e.nit', '=', 'col.nit')
            ->select([
                's.id',
                's.idcampaing',

                // ===== columnas requeridas =====
                's.referencia',
                's.created_at',
                's.documento',
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
                DB::raw("COALESCE(NULLIF(t.nombre,''), CONCAT('Ref ', s.referencia)) as toy_name"),
                'c.nombre as campaign_name',
            ]);

        // Filtros
        if (!empty($campaignId)) {
            $q->where('s.idcampaing', (int) $campaignId);
        }
        if (!empty($referencia)) {
            $q->where('s.referencia', 'like', '%' . str_replace('%', '\%', $referencia) . '%');
        }
        if (!empty($documento)) {
            $q->where('s.documento', 'like', '%' . str_replace('%', '\%', $documento) . '%');
        }
        if (!empty($dateFrom)) {
            $q->whereDate('s.created_at', '>=', $dateFrom);
        }
        if (!empty($dateTo)) {
            $q->whereDate('s.created_at', '<=', $dateTo);
        }

        return $q;
    }
}
