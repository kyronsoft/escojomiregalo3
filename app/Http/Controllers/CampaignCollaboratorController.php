<?php

namespace App\Http\Controllers;

use App\Models\Campaign;
use App\Models\Colaborador;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
// Si usas el Job en emailOne:
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

    /**
     * Actualiza (desde Tabulator) los campos del registro:
     *  - colaboradores: nombre, email
     *  - pivot campaing_colaboradores: sucursal (y opcionalmente email_notified)
     */
    public function updateOne(Campaign $campaign, Request $request)
    {
        $validated = $request->validate([
            'documento'       => ['required', 'string', 'max:15'],
            'nombre'          => ['nullable', 'string', 'max:255'],
            'email'           => ['nullable', 'email', 'max:255'],
            'sucursal'        => ['nullable', 'string', 'max:150'],
            'email_notified'  => ['nullable', 'boolean'], // opcional
        ]);

        $documento = $validated['documento'];

        // Verifica que exista relación en el pivot para esta campaña / nit
        $pivotExists = DB::table(self::PIVOT_TABLE)
            ->where('idcampaign', $campaign->id)
            ->where('nit', (string)$campaign->nit)
            ->where('documento', $documento)
            ->exists();

        if (!$pivotExists) {
            return response()->json(['message' => 'No existe el colaborador en esta campaña.'], 404);
        }

        DB::beginTransaction();
        try {
            // 1) Actualizar colaborador (nombre/email) si existen cambios
            $col = Colaborador::where('documento', $documento)->first();
            if ($col) {
                $dirty = false;
                if (array_key_exists('nombre', $validated) && $validated['nombre'] !== null) {
                    $col->nombre = $validated['nombre'];
                    $dirty = true;
                }
                if (array_key_exists('email', $validated) && $validated['email'] !== null) {
                    $col->email = $validated['email'];
                    $dirty = true;
                }
                if ($dirty) {
                    $col->save();
                }
            }

            // 2) Actualizar pivot (sucursal, email_notified opcional)
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

            // 3) Devolver fila actualizada con el mismo shape que "data()"
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

    public function emailAll(Campaign $campaign, Request $request)
    {
        $request->validate([
            'plantilla' => ['required', 'in:standard,juguetes,navidad'],
        ]);

        // Datos de colaboradores asignados
        $rows = DB::table('campaing_colaboradores as cc')
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
            $email     = trim((string) $r->email);
            $nombre    = (string) $r->nombre;
            $documento = (string) $r->documento;

            // Validación de email
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $skipped++;
                continue;
            }

            // Evitar duplicados en esta corrida
            $emailKey = mb_strtolower($email);
            if (isset($sentEmails[$emailKey])) {
                continue;
            }

            try {
                // 1) Actualizar password del usuario por documento ANTES de enviar correo
                //    Si el usuario no existe, no enviamos el correo y registramos el fallo.
                $affected = DB::table('users')
                    ->where('documento', $documento)
                    ->update([
                        'password'   => Hash::make($documento),
                        'updated_at' => now(),
                    ]);

                if ($affected === 0) {
                    // Usuario no encontrado por documento: no se envía correo
                    Log::warning('Usuario no encontrado para actualización de password', [
                        'documento' => $documento,
                        'email'     => $email,
                        'campaign'  => $campaign->id ?? null,
                    ]);
                    $fail++;
                    continue;
                }

                // 2) Encolar correo con credenciales
                dispatch(new SendWelcomeCredentialsMail(
                    to: $email,
                    name: $nombre,
                    rawPassword: $documento, // la contraseña en texto plano para el correo
                    loginUrl: route('login'),
                    campaignId: $campaign->id,
                    subjectLine: $subject,
                    backgroundImage: $backgroundImage
                ))->onQueue('emails');

                $sentEmails[$emailKey] = true;
                $ok++;

                // 3) Marcar como notificado en pivot
                DB::table(self::PIVOT_TABLE)
                    ->where('idcampaign', $campaign->id)
                    ->where('documento', $documento)
                    ->where('nit', (string) $campaign->nit)
                    ->update([
                        'email_notified' => 1,
                        'updated_at'     => now(),
                    ]);
            } catch (\Throwable $e) {
                Log::error('No se pudo actualizar password o encolar correo de bienvenida+invitación', [
                    'email'    => $email,
                    'documento'=> $documento,
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

    public function emailOne(Campaign $campaign, Request $request)
    {
        $validated = $request->validate([
            'documento' => ['required', 'string', 'max:15'],
            'plantilla' => ['required', 'in:standard,juguetes,navidad'],
        ]);

        // Busca colaborador por documento vinculado a esta campaña
        $row = DB::table(self::PIVOT_TABLE . ' as cc')
            ->join('colaboradores as c', 'c.documento', '=', 'cc.documento')
            ->where('cc.idcampaign', $campaign->id)
            ->where('cc.nit', (string)$campaign->nit)
            ->where('cc.documento', $validated['documento'])
            ->select('cc.documento', 'c.nombre', 'c.email')
            ->first();

        if (!$row || empty($row->email) || !filter_var($row->email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Colaborador sin email válido.'], 422);
        }

        $documento = (string)$row->documento;
        $email = trim((string)$row->email);
        $nombre = (string)$row->nombre;

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
            // 1️⃣ Actualizar password antes del envío
            $affected = DB::table('users')
                ->where('documento', $documento)
                ->update([
                    'password'   => Hash::make($documento),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                // Usuario no encontrado -> no se envía correo
                Log::warning('Usuario no encontrado para actualización de password (envío individual)', [
                    'documento' => $documento,
                    'email'     => $email,
                    'campaign'  => $campaign->id ?? null,
                ]);
                return response()->json(['message' => 'Usuario no encontrado en la tabla users.'], 404);
            }

            // 2️⃣ Enviar correo
            dispatch(new SendWelcomeCredentialsMail(
                to: $email,
                name: $nombre,
                rawPassword: $documento,
                loginUrl: route('login'),
                campaignId: $campaign->id,
                subjectLine: $subject,
                backgroundImage: $bg
            ))->onQueue('emails');

            // 3️⃣ Marcar como notificado
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
        // Asegura el mismo criterio que usas en data(): idcampaign + nit + documento
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


    /**
     * Devuelve una fila con el mismo shape que "data()" para un documento dado.
     */
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
            'nit'            => $r->nit,
            'created_at'     => $r->created_at,
            'updated_at'     => $r->updated_at,
        ];
    }
}
