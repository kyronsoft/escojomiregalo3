<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Imports\ColaboradoresImport;
use Maatwebsite\Excel\Facades\Excel;

class ColaboradorImportController extends Controller
{
    public function showForm()
    {
        return view('colaboradores.import');
    }

    public function import(Request $request)
    {
        $data = $request->validate([
            'idcampaign'        => ['required', 'integer', 'exists:campaigns,id'],
            'file'              => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:20480'],
            'send_notification' => ['nullable', 'boolean'],
        ]);

        $campaign         = Campaign::findOrFail($data['idcampaign']);
        $sendNotification = (bool) ($data['send_notification'] ?? false);

        $import = new ColaboradoresImport($campaign, $sendNotification);
        Excel::import($import, $data['file']);

        return back()
            ->with('success', 'Importación de colaboradores completada.')
            ->with('import_summary', $import->summary());
    }
}
