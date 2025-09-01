<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ImportError;

class ImportErrorController extends Controller
{
    public function index(Request $request)
    {
        // Si la petición es AJAX/JSON devolvemos los datos para Tabulator
        if ($request->wantsJson() || $request->ajax() || $request->boolean('json')) {
            $rows = ImportError::query()
                ->select('id', 'row', 'attribute', 'errors', 'values', 'created_at')
                ->orderByDesc('created_at')
                ->get();

            return response()->json($rows);
        }

        // Vista con el Tabulator
        return view('admin.importerrors.index');
    }
}
