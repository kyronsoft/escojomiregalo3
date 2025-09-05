<?php

namespace App\Http\Controllers;

use App\Models\ErrorEmail;
use Illuminate\Http\Request;

class ErrorEmailController extends Controller
{
    public function index(Request $request)
    {
        // Filtros
        $filters = [
            'idcampaing' => $request->input('idcampaing'),
            'documento'  => $request->input('documento'),
            'email'      => $request->input('email'),
            'from'       => $request->input('from'),
            'to'         => $request->input('to'),
        ];

        $q = ErrorEmail::query()->orderByDesc('created_at');

        if ($request->filled('idcampaing')) {
            $q->where('idcampaing', (int) $request->idcampaing);
        }
        if ($request->filled('documento')) {
            $q->where('documento', 'like', '%' . $request->documento . '%');
        }
        if ($request->filled('email')) {
            $q->where('email', 'like', '%' . $request->email . '%');
        }
        if ($request->filled('from')) {
            $q->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $q->whereDate('created_at', '<=', $request->to);
        }

        $items = $q->paginate(25)->withQueryString();

        return view('erroremails.index', compact('items', 'filters'));
    }
}
