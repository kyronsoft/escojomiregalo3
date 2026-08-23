<?php

namespace App\Imports;

use App\Models\User;
use App\Models\Campaign;
use App\Models\Colaborador;
use App\Models\ColaboradorHijo;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;
use App\Jobs\SendWelcomeCredentialsMail;
use Spatie\Permission\Models\Role;

class ColaboradoresImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation, SkipsOnFailure
{
    protected Campaign $campaign;
    protected string $nit;
    protected int $campaignId;
    protected bool $sendNotification;

    private const PIVOT_TABLE = 'campaing_colaboradores';

    protected array $stats = [
        'colaboradores' => ['creados' => 0, 'actualizados' => 0, 'omitidos' => 0],
        'users'         => ['creados' => 0, 'actualizados' => 0],
        'hijos'         => ['upserts' => 0, 'omitidos_sin_nombre' => 0],
        'pivot'         => ['upserts' => 0],
        'emails'        => ['enviados' => 0, 'omitidos' => 0, 'errores' => 0],
        'errores'       => [],
    ];

    protected array $sentInvites = [];
    protected array $emailByDocumento = [];

    public function __construct(Campaign $campaign, bool $sendNotification = false)
    {
        $this->campaign          = $campaign->loadMissing('empresa');
        $this->nit               = (string) $campaign->nit;
        $this->campaignId        = (int) $campaign->id;
        $this->sendNotification  = $sendNotification;
    }

    /** VALIDACIÓN por fila (Laravel Excel) */
    public function rules(): array
    {
        return [
            // Validamos 'documento' con una regla cerrada que acepta string o numérico
            'documento' => [
                'required',
                function (string $attribute, $value, \Closure $fail) {
                    $doc = $this->normalizeDocumento($value);
                    if ($doc === '') {
                        $fail('El documento es obligatorio.');
                        return;
                    }
                    if (mb_strlen($doc) > 15) {
                        $fail('El documento no puede exceder 15 caracteres.');
                    }
                },
            ],
            'nombre'    => ['required', 'string', 'max:150'],
            'email'     => [
                'nullable',
                'max:150',
                function (string $attribute, $value, \Closure $fail) {
                    $email = trim((string) $value);
                    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $fail('El correo electrónico no es válido.');
                    }
                },
            ],
            'direccion' => ['nullable', 'string', 'max:150'],
            'telefono'  => ['nullable', 'string', 'max:40'],
            'ciudad'    => ['nullable', 'string', 'max:100'],
            'sucursal'  => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * Normaliza el valor del "documento" (acepta string o numérico de Excel) a string.
     * - Si viene numérico, lo formatea sin decimales.
     * - Si Excel lo entrega en notación científica, intenta expandirlo.
     * - Mantiene letras si ya viene como string.
     */
    private function normalizeDocumento($value): string
    {
        if ($value === null) return '';

        // Si ya es string, solo trim
        if (is_string($value)) {
            return trim($value);
        }

        // Si es entero, cast directo a string
        if (is_int($value)) {
            return (string) $value;
        }

        // Si es float (Excel puede traerlo así), evitar decimales/exp. científica
        if (is_float($value)) {
            // sprintf a 0 decimales para quitar .0; si hubiera notación científica, la expande
            // (puede haber pérdida de precisión en números extremadamente largos)
            return rtrim(sprintf('%.0f', $value), '.');
        }

        // Para cualquier otro tipo, convertir y recortar
        return trim((string) $value);
    }


    public function customValidationAttributes(): array
    {
        return [
            'documento' => 'documento',
            'nombre'    => 'nombre',
            'email'     => 'correo electrónico',
            'direccion' => 'dirección',
            'telefono'  => 'teléfono',
            'ciudad'    => 'ciudad',
            'sucursal'  => 'sucursal',
        ];
    }

    /** Captura de fallos de validación: guardar en importerrors */
    public function onFailure(Failure ...$failures)
    {
        foreach ($failures as $f) {
            DB::table('importerrors')->insert([
                'row'        => $f->row(),
                'attribute'  => $f->attribute(),
                'errors'     => Str::limit(implode(' | ', $f->errors()), 255, ''),
                'values'     => Str::limit(json_encode($f->values(), JSON_UNESCAPED_UNICODE), 255, ''),
                'created_at' => now(),
            ]);
        }
    }

    public function collection(Collection $rows)
    {
        foreach ($rows as $index => $row) {
            $row = is_array($row) ? $row : $row->toArray();
            $excelRow  = $index + 2; // encabezados ocupan la fila 1
            $documento = $this->normalizeDocumento($row['documento'] ?? null);
            $nombre    = trim((string)($row['nombre']    ?? ''));
            $email     = trim((string)($row['email']     ?? ''));
            $direccion = trim((string)($row['direccion'] ?? ''));
            $telefono  = trim((string)($row['telefono']  ?? ''));
            $ciudad    = trim((string)($row['ciudad']    ?? ''));
            $sucursal  = trim((string)($row['sucursal']  ?? 'ND'));

            // --- Consolidación de email por DOCUMENTO ---
            if ($email !== '' && isset($this->emailByDocumento[$documento])) {
                // Si aparece un email distinto para el mismo documento, lo registramos y seguimos usando el primero
                if (strcasecmp($this->emailByDocumento[$documento], $email) !== 0) {
                    DB::table('importerrors')->insert([
                        'row'        => $excelRow,
                        'attribute'  => 'email',
                        'errors'     => 'Email distinto para el mismo documento en el archivo. Se usará el primero encontrado.',
                        'values'     => json_encode([
                            'primero' => $this->emailByDocumento[$documento],
                            'nuevo'   => $email,
                            'doc'     => $documento,
                        ], JSON_UNESCAPED_UNICODE),
                        'created_at' => now(),
                    ]);
                }
                $email = $this->emailByDocumento[$documento];
            } elseif ($email !== '') {
                $this->emailByDocumento[$documento] = $email;
            }

            DB::beginTransaction();
            try {
                // === 1) Upsert Colaborador (por documento) ===
                $col = Colaborador::updateOrCreate(
                    ['documento' => $documento],
                    [
                        'nombre'    => $nombre,
                        'email'     => $email !== '' ? $email : null, // consolidado
                        'direccion' => $direccion ?: null,
                        'telefono'  => $telefono ?: null,
                        'ciudad'    => $ciudad ?: null,
                        'nit'       => $this->nit,
                    ]
                );
                $col->wasRecentlyCreated
                    ? $this->stats['colaboradores']['creados']++
                    : $this->stats['colaboradores']['actualizados']++;

                // === 2) Vincular a campaña+empresa (pivot) ===
                DB::table(self::PIVOT_TABLE)->updateOrInsert(
                    [
                        'idcampaign' => $this->campaignId,
                        'documento'  => $documento,
                        'nit'        => $this->nit,
                    ],
                    [
                        'sucursal'       => $sucursal !== '' ? $sucursal : 'ND',
                        'email_notified' => 0,
                        'notify_enabled' => $this->sendNotification ? 1 : 0,
                        'updated_at'     => now(),
                        'created_at'     => now(),
                    ]
                );
                $this->stats['pivot']['upserts']++;

                // === 3) Usuario único por colaborador (login por documento) ===
                $hasValidEmail = $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL);
                $user = User::where('documento', $documento)->first();
                if (!$user && $hasValidEmail) {
                    $user = User::where('email', $email)->first();
                }

                if (!$user) {
                    // Nuevo usuario para este documento. El correo es opcional.
                    $user = User::create([
                        'name'              => $nombre,
                        'email'             => $hasValidEmail ? $email : null,
                        'password'          => Hash::make($documento), // opcional: forzar cambio en primer login
                        'documento'         => $documento,
                        'email_verified_at' => $hasValidEmail ? now() : null,
                        'remember_token'    => Str::random(20),
                    ]);
                    $this->stats['users']['creados']++;
                } else {
                    if ((string)$user->documento === (string)$documento) {
                        // Es el MISMO colaborador → actualiza nombre (no resetea password)
                        $user->forceFill([
                            'name'      => $nombre,
                            'documento' => $documento,
                            'email'     => $hasValidEmail ? $email : $user->email,
                        ])->save();
                        $this->stats['users']['actualizados']++;
                    } else {
                        // Email ya pertenece a otro documento → registramos conflicto y NO tocamos user
                        DB::table('importerrors')->insert([
                            'row'        => $excelRow,
                            'attribute'  => 'email',
                            'errors'     => 'El email ya está asociado a otro usuario (documento diferente).',
                            'values'     => json_encode([
                                'email'         => $email,
                                'doc_en_archivo' => $documento,
                                'doc_en_user'   => $user->documento,
                            ], JSON_UNESCAPED_UNICODE),
                            'created_at' => now(),
                        ]);
                        // Continuar sin modificar el usuario; sí conservamos colaborador/pivot/hijos.
                    }
                }

                // === 4) Rol con Spatie (guard web) ===
                if ($user && (string)$user->documento === (string)$documento) {
                    $role = Role::findOrCreate('colaborador', 'web');
                    if (!$user->hasRole('colaborador')) {
                        $user->assignRole($role);
                    }
                }

                // === 5) Enviar correo de credenciales si está habilitado ===
                if ($this->sendNotification && $user && (string)$user->documento === (string)$documento) {
                    if (!$hasValidEmail) {
                        Log::warning('Notificación omitida en importación de colaboradores: email vacío o inválido', [
                            'documento' => $documento,
                            'email'     => $email,
                            'campaign'  => $this->campaignId,
                            'row'       => $excelRow,
                        ]);
                        $this->stats['emails']['omitidos']++;
                    } else {
                        $emailKey = mb_strtolower($email);
                        if (!isset($this->sentInvites[$emailKey])) {
                            try {
                                dispatch(new SendWelcomeCredentialsMail(
                                    to: $email,
                                    name: $nombre,
                                    rawPassword: $documento,
                                    loginUrl: route('login'),
                                    campaignId: $this->campaignId,
                                    subjectLine: (string)($this->campaign->subject ?? 'Bienvenido'),
                                ))->onQueue('emails');

                                $this->sentInvites[$emailKey] = true;
                                $this->stats['emails']['enviados']++;

                                DB::table(self::PIVOT_TABLE)
                                    ->where('idcampaign', $this->campaignId)
                                    ->where('documento', $documento)
                                    ->where('nit', $this->nit)
                                    ->update(['email_notified' => 1, 'updated_at' => now()]);
                            } catch (\Throwable $mailEx) {
                                DB::table('importerrors')->insert([
                                    'row'        => $excelRow,
                                    'attribute'  => 'erroremail',
                                    'errors'     => Str::limit($mailEx->getMessage(), 255, ''),
                                    'values'     => json_encode(['documento' => $documento, 'email' => $email], JSON_UNESCAPED_UNICODE),
                                    'created_at' => now(),
                                ]);
                                $this->stats['emails']['errores']++;
                            }
                        } else {
                            $this->stats['emails']['omitidos']++;
                        }
                    }
                } elseif (!$this->sendNotification) {
                    $this->stats['emails']['omitidos']++;
                }

                // === 6) Hijos (opcional) ===
                $children = $this->extractChildrenFromRow($row);
                foreach ($children as $child) {
                    $identificacion = (string)($child['identificacion'] ?: $documento);

                    if (trim((string)$child['nombre_hijo']) === '') {
                        $this->stats['hijos']['omitidos_sin_nombre']++;
                        continue;
                    }

                    // en el updateOrCreate de hijos
                    ColaboradorHijo::updateOrCreate(
                        [
                            'identificacion' => $identificacion,
                            'nombre_hijo'    => (string)$child['nombre_hijo'],
                            'idcampaing'     => (int)$this->campaignId,
                        ],
                        [
                            'genero'     => $this->normalizeGenero($child['genero'] ?? null),
                            'rango_edad' => $this->normalizeEdad($child['rango_edad'] ?? null), // <- entero o null
                        ]
                    );


                    $this->stats['hijos']['upserts']++;
                }

                DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();

                DB::table('importerrors')->insert([
                    'row'        => $excelRow,
                    'attribute'  => 'row',
                    'errors'     => Str::limit($e->getMessage(), 255, ''),
                    'values'     => Str::limit(json_encode($row, JSON_UNESCAPED_UNICODE), 255, ''),
                    'created_at' => now(),
                ]);
                $this->stats['errores'][] = $e->getMessage();
            }
        }
    }

    public function summary(): array
    {
        return $this->stats;
    }

    /** Igual que antes: normaliza columnas de hijos si vienen en el archivo */
    protected function extractChildrenFromRow(array $row): array
    {
        // Normaliza llaves a minúsculas con _ (por si no usaran HeadingRow slug)
        $lower = [];
        foreach ($row as $k => $v) {
            $key = strtolower(trim((string)$k));
            $key = preg_replace('/\s+/', '_', $key);
            $lower[$key] = is_string($v) ? trim($v) : $v;
        }

        $children = [];

        // Si viene todo en la misma fila (formato simple): hijo + edad + genero
        $hasDirect = !empty($lower['hijo'])            // 👈 alias de nombre_hijo
            || !empty($lower['nombre_hijo'])
            || !empty($lower['edad'])            // 👈 alias de rango_edad
            || !empty($lower['rango_edad'])
            || !empty($lower['rango'])
            || !empty($lower['genero'])
            || !empty($lower['sexo']);

        if ($hasDirect) {
            $nombreHijo = (string)($lower['nombre_hijo'] ?? $lower['hijo'] ?? '');
            $rangoEdad  = (string)($lower['rango_edad']  ?? $lower['edad'] ?? $lower['rango'] ?? '');
            $generoRaw  = (string)($lower['genero'] ?? $lower['sexo'] ?? '');

            $children[] = [
                'identificacion' => (string)($lower['hijo_identificacion'] ?? $lower['hijo_documento'] ?? $lower['identificacion'] ?? $lower['documento'] ?? ''),
                'nombre_hijo'    => $nombreHijo,
                'genero'         => $this->normalizeGenero($generoRaw), // 👈 normalizado
                'rango_edad'     => $rangoEdad,
            ];
        }

        // Buckets tipo nombre_hijo_1, genero_1, edad_1 ...
        $buckets = [];
        foreach ($lower as $key => $value) {
            // Caso: <campo>_<n>
            if (preg_match('/^(nombre_hijo|hijo|genero|sexo|rango_edad|edad|rango)_(\d+)$/', $key, $m)) { // 👈 añadimos hijo/edad
                $fld = $m[1];
                $idx = (int)$m[2];
                $map = [
                    'nombre_hijo' => 'nombre_hijo',
                    'hijo'        => 'nombre_hijo', // 👈 alias
                    'genero'      => 'genero',
                    'sexo'        => 'genero',
                    'rango_edad'  => 'rango_edad',
                    'edad'        => 'rango_edad',  // 👈 alias
                    'rango'       => 'rango_edad',
                ];
                $target = $map[$fld] ?? null;
                if ($target) {
                    $buckets[$idx][$target] = is_string($value) ? trim($value) : $value;
                }
                continue;
            }

            // Caso: hijo_<n>_<campo>
            if (preg_match('/^hijo_?(\d+)_(identificacion|documento|nombre|genero|sexo|edad|rango(?:_edad)?)$/', $key, $m)) { // 👈 añadimos edad
                $idx = (int)$m[1];
                $fld = $m[2];
                $map = [
                    'identificacion' => 'identificacion',
                    'documento'      => 'identificacion',
                    'nombre'         => 'nombre_hijo',
                    'genero'         => 'genero',
                    'sexo'           => 'genero',
                    'edad'           => 'rango_edad', // 👈 alias
                    'rango'          => 'rango_edad',
                    'rango_edad'     => 'rango_edad',
                ];
                $target = $map[$fld] ?? null;
                if ($target) {
                    $buckets[$idx][$target] = is_string($value) ? trim($value) : $value;
                }
            }
        }

        if (!empty($buckets)) {
            ksort($buckets);
            foreach ($buckets as $idx => $data) {
                $c = array_merge(
                    ['identificacion' => '', 'nombre_hijo' => '', 'genero' => null, 'rango_edad' => ''],
                    $data
                );

                // Normaliza género bucket
                $c['genero'] = $this->normalizeGenero($c['genero'] ?? null);

                if (($c['nombre_hijo'] ?? '') !== '') $children[] = $c;
            }
        }

        // Limpieza final
        foreach ($children as &$c) {
            $c['identificacion'] = (string)($c['identificacion'] ?: '');
            $c['nombre_hijo']    = (string)($c['nombre_hijo']    ?: '');
            // $c['genero'] ya viene normalizado a 'F'/'M'/null
            $c['rango_edad']     = ($c['rango_edad'] ?? '') === '' ? '' : (string)$c['rango_edad'];
        }
        unset($c);

        // Solo hijos con nombre
        return array_values(array_filter($children, fn($c) => $c['nombre_hijo'] !== ''));
    }


    /** Normaliza género a 'NIÑO', 'NIÑA' o 'UNISEX' */
    private function normalizeGenero($value): string
    {
        if ($value === null) return 'UNISEX';

        $g = strtoupper(trim((string)$value));

        // Variantes comunes para niña
        $nina = ['F', 'FEMENINO', 'FEMENINA', 'NIÑA', 'NINA', 'GIRL', 'MUJER', 'FEMALE'];
        // Variantes comunes para niño
        $nino = ['M', 'MASCULINO', 'MASCULINA', 'NIÑO', 'NINO', 'BOY', 'HOMBRE', 'MALE'];
        // Variantes de unisex
        $uni  = ['UNISEX', 'UNI', 'U', 'AMBOS', 'MIXTO', 'BEBE', 'BEBÉ', 'BEBE/NIÑO', 'BEBE/NIÑA'];

        if (in_array($g, $nina, true)) return 'NIÑA';
        if (in_array($g, $nino, true)) return 'NIÑO';
        if (in_array($g, $uni,  true)) return 'UNISEX';

        // Si llega cualquier otra cosa, considera UNISEX
        return 'UNISEX';
    }

    /** Devuelve un entero 0–14 o NULL. Acepta "7", "7-9", "0–3", "8 años", "12+" ... */
    private function normalizeEdad($value): ?int
    {
        if ($value === null) return null;

        // Si viene numérico puro (int/float)
        if (is_int($value) || is_float($value)) {
            $n = (int)floor($value);
            return ($n < 0) ? 0 : (($n > 14) ? 14 : $n);
        }

        // Si viene como string, busca el primer número y úsalo
        $s = trim((string)$value);
        if ($s === '') return null;

        // Caso "7-9" / "0–3": toma el primer número encontrado
        if (preg_match('/\d+/', $s, $m)) {
            $n = (int)$m[0];
            if ($n < 0) $n = 0;
            if ($n > 14) $n = 14;
            return $n;
        }

        return null;
    }
}
