<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

// Para exportar a Excel (xlsx)
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\FromCollection;

class SeleccionadosController extends Controller
{

    public function index(Request $request)
    {
        // Tamaño de página seguro
        $perPage = (int) $request->input('per_page', 25);
        $perPage = max(5, min($perPage, 200));

        // NIT del usuario logueado
        $userNit = Auth::user()?->nit;

        // Campañas visibles SOLO por NIT del usuario
        $campaigns = Campaign::query()
            ->when($userNit, fn($q) => $q->where('nit', $userNit))
            ->orderBy('updated_at', 'desc')
            ->get(['id', 'nombre', 'nit']); // incluyo 'nit' para validación

        // mantener valores en inputs (aceptando ambas variantes del parámetro)
        $campaignId = $request->input('idcampaign', $request->input('idcampaing'));
        $referencia = $request->input('referencia');
        $documento  = $request->input('documento');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');

        // Validar que el idcampaign seleccionado pertenezca a las campañas del usuario
        if (!empty($campaignId) && !$campaigns->firstWhere('id', (int) $campaignId)) {
            $campaignId = null; // si no pertenece, lo anulamos
        }

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
            'documento'    => 'cc.documento',
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
            'selected'     => DB::raw('CASE WHEN s.id IS NULL THEN 0 ELSE 1 END'),
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
            // Por defecto: seleccionados recientes arriba; luego por nombre del colaborador y del hijo
            $q->orderBy(DB::raw('CASE WHEN s.created_at IS NULL THEN 1 ELSE 0 END')) // seleccionados primero
                ->orderByDesc('s.created_at')
                ->orderBy('col.nombre')
                ->orderBy('h.nombre_hijo');
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
        $campaignId = $request->input('idcampaign', $request->input('idcampaing'));

        if (empty($campaignId)) {
            abort(422, 'Debe seleccionar una campaña antes de exportar.');
        }

        // Verificar que la campaña exista y pertenezca al usuario
        $user    = Auth::user();
        $isAdmin = $user?->hasRole('Admin');
        $belongs = Campaign::query()
            ->where('id', (int) $campaignId)
            ->when(!$isAdmin, fn($q) => $q->where('nit', $user?->nit))
            ->exists();

        if (!$belongs) {
            abort(403, 'La campaña seleccionada no existe o no tiene acceso a ella.');
        }

        // Misma base query, sin paginar, y orden coherente
        $q = $this->baseQuery($request)
            ->orderBy(DB::raw('CASE WHEN s.created_at IS NULL THEN 1 ELSE 0 END'))
            ->orderByDesc('s.created_at')
            ->orderBy('col.nombre')
            ->orderBy('h.nombre_hijo');

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
                $r->referencia,     // Referencia (puede venir null si no ha seleccionado)
                $fecha,             // Fecha Selección
                $r->documento,      // Documento
                $r->colaborador,    // Colaborador
                $r->politicadatos,    // Politica Datos
                $r->telefono,       // Telefono
                $r->nombre_hijo,    // Nombre Hijo (incluye aunque no haya selección)
                $r->genero_hijo,    // Genero
                $r->rango_edad,     // Rango Edad
                $r->direccion,      // Dirección
                $r->indicaciones,   // Indicaciones
                $r->ciudad,         // Codigo Ciudad
                $r->departamento,   // Departamento
                $r->sucursal,       // Sucursal
                $r->email,          // Email
                $r->empresa,        // Empresa
            ];
        });

        $headings = [
            'Referencia',
            'Fecha Selección',
            'Documento',
            'Colaborador',
            'Politica Datos',
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
     * Parte de la asignación de colaboradores (cc) y une TODOS los hijos (h) del colaborador,
     * luego enlaza la selección (s) por hijo y campaña.
     */
    private function baseQuery(Request $request)
    {
        $campaignId = $request->input('idcampaign', $request->input('idcampaing'));
        $referencia = $request->input('referencia');
        $documento  = $request->input('documento');
        $dateFrom   = $request->input('date_from');
        $dateTo     = $request->input('date_to');

        $user          = Auth::user();
        $userNit       = $user?->nit;
        $isRrhhCliente = $user?->hasRole('RRHH-Cliente');

        // Para RRHH-Cliente: validar que el idcampaign pertenezca a su NIT antes de aplicarlo
        if ($isRrhhCliente && $userNit && !empty($campaignId)) {
            $campaignBelongsToNit = DB::table('campaigns')
                ->where('id', (int) $campaignId)
                ->where('nit', $userNit)
                ->exists();
            if (!$campaignBelongsToNit) {
                $campaignId = null;
            }
        }
        $user    = Auth::user();
        $isAdmin = $user?->hasRole('Admin');

        $q = DB::table('campaing_colaboradores as cc')
            // Colaborador asignado a la campaña
            ->leftJoin('colaboradores as col', 'col.documento', '=', 'cc.documento')

            // TODOS los hijos del colaborador (aunque no hayan seleccionado)
            ->leftJoin('colaborador_hijos as h', 'h.identificacion', '=', 'cc.documento')

            // Selección por hijo y campaña (si existe)
            ->leftJoin('seleccionados as s', function ($join) {
                $join->on('s.idhijo', '=', 'h.id')
                    ->on('s.idcampaing', '=', 'cc.idcampaign');
            })

            // Datos de campaña / juguetes para nombre de juguete
            ->leftJoin('campaigns as c', 'c.id', '=', 'cc.idcampaign')
            ->leftJoin('campaign_toys as t', function ($join) {
                $join->on('t.idcampaign', '=', 'cc.idcampaign')
                    ->on('t.referencia',  '=', 's.referencia');
            })

            ->leftJoin('ciudades as ciu', 'ciu.codigo', '=', 'col.ciudad')
            ->leftJoin('empresas as e', 'e.nit', '=', 'col.nit')

            ->select([
                's.id',
                DB::raw('cc.idcampaign as idcampaing'),

                // ===== columnas requeridas =====
                's.referencia',           // puede ser NULL si el hijo no ha seleccionado
                's.created_at',           // fecha de selección (NULL si no hay selección)
                DB::raw('cc.documento as documento'),
                'col.nombre as colaborador',
                'col.telefono',
                'col.politicadatos',
                'h.nombre_hijo',
                'h.genero as genero_hijo',
                'h.rango_edad',
                'col.direccion',
                DB::raw("COALESCE(NULLIF(col.observaciones,''), '') as indicaciones"),
                'col.ciudad',
                'ciu.departamento',
                'cc.sucursal',
                'col.email',
                DB::raw("COALESCE(NULLIF(e.nombre,''), c.nombre) as empresa"),

                // extra opcional de juguete
                DB::raw("COALESCE(NULLIF(t.nombre,''), CASE WHEN s.referencia IS NOT NULL THEN CONCAT('Ref ', s.referencia) ELSE '' END) as toy_name"),
                'c.nombre as campaign_name',

                // útil para UI
                DB::raw('CASE WHEN s.id IS NULL THEN 0 ELSE 1 END as selected'),
            ]);

        // Restringir RRHH-Cliente a los datos de su propio NIT
        if ($isRrhhCliente && $userNit) {
            $q->where('cc.nit', $userNit);
        // ===== Scope de seguridad: no-Admin solo ve su NIT =====
        if (!$isAdmin && $user?->nit) {
            $q->where('cc.nit', $user->nit);
        }

        // ===== Filtros =====
        if (!empty($campaignId)) {
            $q->where('cc.idcampaign', (int) $campaignId);
        }

        if (!empty($referencia)) {
            // Filtra solo los que tengan esa referencia seleccionada
            $ref = str_replace('%', '\%', $referencia);
            $q->where('s.referencia', 'like', '%' . $ref . '%');
        }

        if (!empty($documento)) {
            $doc = str_replace('%', '\%', $documento);
            $q->where('cc.documento', 'like', '%' . $doc . '%');
        }

        // Rango de fechas sobre la selección; conserva no seleccionados
        if (!empty($dateFrom)) {
            $q->where(function ($w) use ($dateFrom) {
                $w->whereDate('s.created_at', '>=', $dateFrom)
                    ->orWhereNull('s.created_at');
            });
        }
        if (!empty($dateTo)) {
            $q->where(function ($w) use ($dateTo) {
                $w->whereDate('s.created_at', '<=', $dateTo)
                    ->orWhereNull('s.created_at');
            });
        }

        return $q;
    }
}
