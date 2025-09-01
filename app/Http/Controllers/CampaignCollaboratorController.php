<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Colaborador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Jobs\SendWelcomeCredentialsMail;

class CampaignCollaboratorController extends Controller
{
    private const PIVOT_TABLE = 'campaing_colaboradores';

    public function index(Campaign $campaign)
    {
        return view('campaigns.collaborators', [
            'campaign' => $campaign,
        ]);
    }

    /**
     * Devuelve JSON para Tabulator con los colaboradores asignados a la campaña.
     */
    public function data(Campaign $campaign, Request $request)
    {
        $pivot = self::PIVOT_TABLE . ' as cc';
        $colabTable = (new Colaborador)->getTable() . ' as c'; // respeta el $table del modelo

        $rows = DB::table($pivot)
            ->join($colabTable, 'c.documento', '=', 'cc.documento')
            ->where('cc.idcampaign', $campaign->id)
            ->where('cc.nit', (string) $campaign->nit)
            ->select([
                'cc.documento',
                'c.nombre',
                'c.email',
                'cc.sucursal',
                'cc.email_notified',
                'cc.nit',
                'cc.created_at',
                'cc.updated_at',
            ])
            ->orderBy('c.nombre')
            ->get()
            ->map(function ($r) {
                $r->email_notified = (bool) $r->email_notified;
                return $r;
            });

        return response()->json($rows);
    }

    public function emailAll(Campaign $campaign, Request $request)
    {
        $request->validate([
            'plantilla' => ['required', 'in:standard,juguetes,navidad'],
        ]);

        // Datos de colaboradores asignados
        $rows = \Illuminate\Support\Facades\DB::table('campaing_colaboradores as cc')
            ->join('colaboradores as c', 'c.documento', '=', 'cc.documento')
            ->where('cc.idcampaign', $campaign->id)
            ->where('cc.nit', (string) $campaign->nit)
            ->select(['cc.documento', 'c.nombre', 'c.email'])
            ->get();

        // Subject EXACTO desde campaigns.subject
        $subject = (string) ($campaign->subject ?? 'Invitación');

        // Imagen de fondo según plantilla elegida
        $tpl = strtolower((string) $request->input('plantilla', 'standard'));
        $backgroundImage = null;
        if ($tpl === 'juguetes') {
            $backgroundImage = asset('assets/images/moreproducts/background-toys.jpg');
        } elseif ($tpl === 'navidad') {
            $backgroundImage = asset('assets/images/moreproducts/background-navidad.jpg');
        }

        $sentEmails = [];
        $ok = 0;
        $fail = 0;
        $skipped = 0;

        foreach ($rows as $r) {
            $email     = trim((string)$r->email);
            $nombre    = (string)$r->nombre;
            $documento = (string)$r->documento;

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            $emailKey = mb_strtolower($email);
            if (isset($sentEmails[$emailKey])) {
                continue; // ya encolado en esta corrida
            }

            try {
                dispatch(new \App\Jobs\SendWelcomeCredentialsMail(
                    to: $email,
                    name: $nombre,
                    rawPassword: $documento,
                    loginUrl: route('login'),
                    campaignId: $campaign->id,
                    subjectLine: $subject,
                    backgroundImage: $backgroundImage // <-- NUEVO parámetro opcional
                ))->onQueue('emails');

                $sentEmails[$emailKey] = true;
                $ok++;

                // Marcar como notificado en pivot
                \Illuminate\Support\Facades\DB::table('campaing_colaboradores')
                    ->where('idcampaign', $campaign->id)
                    ->where('documento', $documento)
                    ->where('nit', (string)$campaign->nit)
                    ->update([
                        'email_notified' => 1,
                        'updated_at'     => now(),
                    ]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('No se pudo encolar correo de bienvenida+invitación', [
                    'email'    => $email,
                    'campaign' => $campaign->id ?? null,
                    'error'    => $e->getMessage(),
                ]);
                $fail++;
            }
        }

        return response()->json([
            'message' => 'Envío iniciado.',
            'total'   => $rows->count(),
            'sent'    => $ok,
            'failed'  => $fail,
            'skipped' => $skipped,
        ], 200);
    }

    public function emailOne(Campaign $campaign, \Illuminate\Http\Request $request)
    {
        $validated = $request->validate([
            'documento' => ['required', 'string', 'max:15'],
            'plantilla' => ['required', 'in:standard,juguetes,navidad'],
        ]);

        // Busca colaborador por documento vinculado a esta campaña
        $row = \DB::table('campaing_colaboradores as cc')
            ->join('colaboradores as c', 'c.documento', '=', 'cc.documento')
            ->where('cc.idcampaign', $campaign->id)
            ->where('cc.nit', (string)$campaign->nit)
            ->where('cc.documento', $validated['documento'])
            ->select('cc.documento', 'c.nombre', 'c.email')
            ->first();

        if (!$row || empty($row->email) || !filter_var($row->email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Colaborador sin email válido.'], 422);
        }

        // Subject de la campaña
        $subject = (string)($campaign->subject ?? 'Invitación');

        // Background por plantilla
        $bg = null;
        if ($validated['plantilla'] === 'juguetes') {
            $bg = asset('assets/images/moreproducts/background-toys.jpg');
        } elseif ($validated['plantilla'] === 'navidad') {
            $bg = asset('assets/images/moreproducts/background-navidad.jpg');
        }

        try {
            dispatch(new SendWelcomeCredentialsMail(
                to: $row->email,
                name: (string)$row->nombre,
                rawPassword: (string)$row->documento,
                loginUrl: route('login'),
                campaignId: $campaign->id,
                subjectLine: $subject,
                backgroundImage: $bg
            ))->onQueue('emails');

            // Marca como notificado
            \DB::table('campaing_colaboradores')
                ->where('idcampaign', $campaign->id)
                ->where('documento', $validated['documento'])
                ->where('nit', (string)$campaign->nit)
                ->update(['email_notified' => 1, 'updated_at' => now()]);

            return response()->json(['message' => 'Correo enviado', 'notified' => true], 200);
        } catch (\Throwable $e) {
            \Log::error('No se pudo encolar correo individual', ['campaign' => $campaign->id, 'documento' => $validated['documento'], 'e' => $e->getMessage()]);
            return response()->json(['message' => 'No se pudo encolar el correo.'], 500);
        }
    }
}
