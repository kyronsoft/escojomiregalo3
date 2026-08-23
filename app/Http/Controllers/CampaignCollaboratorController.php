<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Colaborador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    public function data(Campaign $campaign, Request $request)
    {
        $pivot = self::PIVOT_TABLE . ' as cc';
        $colabTable = (new Colaborador)->getTable() . ' as c';

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
                'cc.notify_enabled',
                'cc.nit',
                'cc.created_at',
                'cc.updated_at',
            ])
            ->orderBy('c.nombre')
            ->get()
            ->map(function ($r) {
                $r->email_notified  = (bool) $r->email_notified;
                $r->notify_enabled  = (bool) $r->notify_enabled;
                return $r;
            });

        return response()->json($rows);
    }

    public function updateOne(Campaign $campaign, Request $request)
    {
        $validated = $request->validate([
            'documento'      => ['required', 'string', 'max:15'],
            'nombre'         => ['nullable', 'string', 'max:255'],
            'email'          => ['nullable', 'email', 'max:255'],
            'sucursal'       => ['nullable', 'string', 'max:150'],
            'email_notified' => ['nullable', 'boolean'],
        ]);

        $documento = $validated['documento'];

        $pivotExists = DB::table(self::PIVOT_TABLE)
            ->where('idcampaign', $campaign->id)
            ->where('nit', (string)$campaign->nit)
            ->where('documento', $documento)
            ->exists();

        if (!$pivotExists) {
            return response()->json(['message' => 'No existe el colaborador en esta campaña.'], 404);
        }

        $emailChanged = array_key_exists('email', $validated) && $validated['email'] !== null;
        $linkedUser = $emailChanged ? DB::table('users')->where('documento', $documento)->first() : null;

        if ($emailChanged) {
            $emailTaken = DB::table('users')
                ->where('email', $validated['email'])
                ->when($linkedUser, fn ($q) => $q->where('id', '!=', $linkedUser->id))
                ->exists();

            if ($emailTaken) {
                return response()->json(['message' => 'Ese correo ya está en uso por otro usuario.'], 422);
            }
        }

        DB::beginTransaction();
        try {
            $col = Colaborador::where('documento', $documento)->first();
            if ($col) {
                $dirty = false;
                if (array_key_exists('nombre', $validated) && $validated['nombre'] !== null) {
                    $col->nombre = $validated['nombre'];
                    $dirty = true;
                }
                if ($emailChanged) {
                    $col->email = $validated['email'];
                    $dirty = true;
                }
                if ($dirty) $col->save();
            }

            if ($emailChanged && $linkedUser) {
                DB::table('users')
                    ->where('id', $linkedUser->id)
                    ->update([
                        'email'      => $validated['email'],
                        'updated_at' => now(),
                    ]);
            }

            $pivotUpdate = ['updated_at' => now()];
            if (array_key_exists('sucursal', $validated)) {
                $pivotUpdate['sucursal'] = $validated['sucursal'];
            }
            if (array_key_exists('email_notified', $validated)) {
                $pivotUpdate['email_notified'] = $validated['email_notified'] ? 1 : 0;
            }

            if (count($pivotUpdate) > 1) {
                DB::table(self::PIVOT_TABLE)
                    ->where('idcampaign', $campaign->id)
                    ->where('nit', (string)$campaign->nit)
                    ->where('documento', $documento)
                    ->update($pivotUpdate);
            }

            DB::commit();

            $row = $this->oneRowPayload($campaign, $documento);
            if (!$row) {
                return response()->json(['message' => 'Actualizado, pero no se pudo recargar la fila.'], 200);
            }

            return response()->json([
                'message'     => 'Registro actualizado.',
                'updated_row' => $row,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Error updateOne colaboradores', [
                'campaign'  => $campaign->id,
                'documento' => $documento,
                'e'         => $e->getMessage(),
            ]);
            return response()->json(['message' => 'No fue posible actualizar el registro.'], 500);
        }
    }

    /**
     * Activa o desactiva la preferencia de notificación de un colaborador en la campaña.
     */
    public function toggleNotify(Campaign $campaign, Request $request)
    {
        $validated = $request->validate([
            'documento'      => ['required', 'string', 'max:15'],
            'notify_enabled' => ['required', 'boolean'],
        ]);

        $documento = $validated['documento'];

        $affected = DB::table(self::PIVOT_TABLE)
            ->where('idcampaign', $campaign->id)
            ->where('nit', (string)$campaign->nit)
            ->where('documento', $documento)
            ->update([
                'notify_enabled' => $validated['notify_enabled'] ? 1 : 0,
                'updated_at'     => now(),
            ]);

        if ($affected === 0) {
            return response()->json(['message' => 'Colaborador no encontrado en esta campaña.'], 404);
        }

        return response()->json([
            'message'        => 'Preferencia de notificación actualizada.',
            'notify_enabled' => (bool) $validated['notify_enabled'],
        ], 200);
    }

    public function emailAll(Campaign $campaign, Request $request)
    {
        $request->validate([
            'plantilla' => ['required', 'in:standard,juguetes,navidad'],
        ]);

        // Solo colaboradores con notificación habilitada
        $rows = DB::table('campaing_colaboradores as cc')
            ->join('colaboradores as c', 'c.documento', '=', 'cc.documento')
            ->where('cc.idcampaign', $campaign->id)
            ->where('cc.nit', (string) $campaign->nit)
            ->where('cc.notify_enabled', 1)
            ->select(['cc.documento', 'c.nombre', 'c.email'])
            ->get();

        $subject = (string) ($campaign->subject ?? 'Invitación');

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
            $email     = trim((string) $r->email);
            $nombre    = (string) $r->nombre;
            $documento = (string) $r->documento;

            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Log::warning('Notificación masiva omitida: colaborador sin email válido', [
                    'documento' => $documento,
                    'email'     => $email,
                    'campaign'  => $campaign->id ?? null,
                ]);

                DB::table('importerrors')->insert([
                    'row'        => 0,
                    'attribute'  => 'email',
                    'errors'     => "Email inválido o vacío para documento {$documento}.",
                    'values'     => json_encode(['documento' => $documento, 'email' => $email, 'campaign' => $campaign->id]),
                    'created_at' => now(),
                ]);
                $skipped++;
                continue;
            }

            $emailKey = mb_strtolower($email);
            if (isset($sentEmails[$emailKey])) {
                continue;
            }

            try {
                $affected = DB::table('users')
                    ->where('documento', $documento)
                    ->update([
                        'password'   => Hash::make($documento),
                        'updated_at' => now(),
                    ]);

                if ($affected === 0) {
                    Log::warning('Usuario no encontrado para actualización de password', [
                        'documento' => $documento,
                        'email'     => $email,
                        'campaign'  => $campaign->id ?? null,
                    ]);
                    $fail++;
                    continue;
                }

                dispatch(new SendWelcomeCredentialsMail(
                    to: $email,
                    name: $nombre,
                    rawPassword: $documento,
                    loginUrl: route('login'),
                    campaignId: $campaign->id,
                    subjectLine: $subject,
                    backgroundImage: $backgroundImage
                ))->onQueue('emails');

                $sentEmails[$emailKey] = true;
                $ok++;

                DB::table(self::PIVOT_TABLE)
                    ->where('idcampaign', $campaign->id)
                    ->where('documento', $documento)
                    ->where('nit', (string) $campaign->nit)
                    ->update([
                        'email_notified' => 1,
                        'updated_at'     => now(),
                    ]);
            } catch (\Throwable $e) {
                DB::table('importerrors')->insert([
                    'row'        => 0,
                    'attribute'  => 'email',
                    'errors'     => substr($e->getMessage(), 0, 255),
                    'values'     => json_encode(['documento' => $documento, 'email' => $email, 'campaign' => $campaign->id]),
                    'created_at' => now(),
                ]);
                Log::error('No se pudo actualizar password o encolar correo', [
                    'email'     => $email,
                    'documento' => $documento,
                    'campaign'  => $campaign->id ?? null,
                    'error'     => $e->getMessage(),
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

    public function emailOne(Campaign $campaign, Request $request)
    {
        $validated = $request->validate([
            'documento' => ['required', 'string', 'max:15'],
            'plantilla' => ['required', 'in:standard,juguetes,navidad'],
        ]);

        $row = DB::table(self::PIVOT_TABLE . ' as cc')
            ->join('colaboradores as c', 'c.documento', '=', 'cc.documento')
            ->where('cc.idcampaign', $campaign->id)
            ->where('cc.nit', (string)$campaign->nit)
            ->where('cc.documento', $validated['documento'])
            ->select('cc.documento', 'c.nombre', 'c.email', 'cc.notify_enabled')
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Colaborador no encontrado en esta campaña.'], 404);
        }

        if (!$row->notify_enabled) {
            return response()->json([
                'message'         => 'Este colaborador tiene la notificación deshabilitada. Actívala antes de reenviar.',
                'notify_disabled' => true,
            ], 422);
        }

        if (empty($row->email) || !filter_var($row->email, FILTER_VALIDATE_EMAIL)) {
            Log::warning('Notificación individual omitida: colaborador sin email válido', [
                'documento' => $row->documento,
                'email'     => $row->email,
                'campaign'  => $campaign->id ?? null,
            ]);

            return response()->json(['message' => 'Colaborador sin email válido. No se envió notificación.'], 422);
        }

        $documento = (string)$row->documento;
        $email = trim((string)$row->email);
        $nombre = (string)$row->nombre;

        $subject = (string)($campaign->subject ?? 'Invitación');

        $bg = null;
        if ($validated['plantilla'] === 'juguetes') {
            $bg = asset('assets/images/moreproducts/background-toys.jpg');
        } elseif ($validated['plantilla'] === 'navidad') {
            $bg = asset('assets/images/moreproducts/background-navidad.jpg');
        }

        try {
            $affected = DB::table('users')
                ->where('documento', $documento)
                ->update([
                    'password'   => Hash::make($documento),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                Log::warning('Usuario no encontrado para actualización de password (envío individual)', [
                    'documento' => $documento,
                    'email'     => $email,
                    'campaign'  => $campaign->id ?? null,
                ]);
                return response()->json(['message' => 'Usuario no encontrado en la tabla users.'], 404);
            }

            dispatch(new SendWelcomeCredentialsMail(
                to: $email,
                name: $nombre,
                rawPassword: $documento,
                loginUrl: route('login'),
                campaignId: $campaign->id,
                subjectLine: $subject,
                backgroundImage: $bg
            ))->onQueue('emails');

            DB::table(self::PIVOT_TABLE)
                ->where('idcampaign', $campaign->id)
                ->where('documento', $documento)
                ->where('nit', (string)$campaign->nit)
                ->update([
                    'email_notified' => 1,
                    'updated_at'     => now(),
                ]);

            return response()->json(['message' => 'Correo enviado', 'notified' => true], 200);
        } catch (\Throwable $e) {
            DB::table('importerrors')->insert([
                'row'        => 0,
                'attribute'  => 'email',
                'errors'     => substr($e->getMessage(), 0, 255),
                'values'     => json_encode(['documento' => $documento, 'email' => $email, 'campaign' => $campaign->id]),
                'created_at' => now(),
            ]);
            Log::error('No se pudo actualizar password o encolar correo individual', [
                'campaign'  => $campaign->id,
                'documento' => $documento,
                'email'     => $email,
                'error'     => $e->getMessage(),
            ]);
            return response()->json(['message' => 'No se pudo encolar el correo.'], 500);
        }
    }

    public function destroy(\App\Models\Campaign $campaign, string $documento)
    {
        $affected = \DB::table(self::PIVOT_TABLE)
            ->where('idcampaign', $campaign->id)
            ->where('nit', (string) $campaign->nit)
            ->where('documento', $documento)
            ->delete();

        if ($affected > 0) {
            return response()->json([
                'ok'      => true,
                'message' => 'Colaborador eliminado de la campaña.'
            ], 200);
        }

        return response()->json([
            'ok'      => false,
            'message' => 'Registro no encontrado o ya había sido eliminado.'
        ], 404);
    }

    private function oneRowPayload(Campaign $campaign, string $documento): ?array
    {
        $r = DB::table(self::PIVOT_TABLE . ' as cc')
            ->join('colaboradores as c', 'c.documento', '=', 'cc.documento')
            ->where('cc.idcampaign', $campaign->id)
            ->where('cc.nit', (string)$campaign->nit)
            ->where('cc.documento', $documento)
            ->select([
                'cc.documento',
                'c.nombre',
                'c.email',
                'cc.sucursal',
                'cc.email_notified',
                'cc.notify_enabled',
                'cc.nit',
                'cc.created_at',
                'cc.updated_at',
            ])
            ->first();

        if (!$r) return null;

        return [
            'documento'      => $r->documento,
            'nombre'         => $r->nombre,
            'email'          => $r->email,
            'sucursal'       => $r->sucursal,
            'email_notified' => (bool)$r->email_notified,
            'notify_enabled' => (bool)$r->notify_enabled,
            'nit'            => $r->nit,
            'created_at'     => $r->created_at,
            'updated_at'     => $r->updated_at,
        ];
    }
}
