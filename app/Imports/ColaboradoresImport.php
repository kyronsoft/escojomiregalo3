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
use App\Jobs\SendWelcomeCredentialsMail;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Validators\Failure;
use Spatie\Permission\Models\Role;

class ColaboradoresImport implements ToCollection, WithHeadingRow, SkipsEmptyRows, WithValidation, SkipsOnFailure
{
    protected Campaign $campaign;
    protected string $nit;
    protected int $campaignId;

    /** Cambia aquí si tu tabla real es 'ccampaing_colaboradores' */
    private const PIVOT_TABLE = 'campaing_colaboradores';

    protected array $stats = [
        'colaboradores' => ['creados' => 0, 'actualizados' => 0, 'omitidos' => 0],
        'users'         => ['creados' => 0, 'actualizados' => 0],
        'hijos'         => ['upserts' => 0, 'omitidos_sin_nombre' => 0],
        'pivot'         => ['upserts' => 0],
        'emails'        => ['bienvenida_enviados' => 0, 'errores' => 0],
        'errores'       => [],
    ];

    /** Evita invitar dos veces al mismo correo en la misma corrida */
    protected array $sentInvites = [];

    /** Consolidación de email por documento (múltiples filas del mismo colaborador) */
    protected array $emailByDocumento = [];

    public function __construct(Campaign $campaign)
    {
        $this->campaign   = $campaign->loadMissing('empresa');
        $this->nit        = (string) $campaign->nit;
        $this->campaignId = (int) $campaign->id;
    }

    /** VALIDACIÓN por fila (Laravel Excel) */
    public function rules(): array
    {
        return [
            'documento' => ['required', 'string', 'max:15'],
            'nombre'    => ['required', 'string', 'max:150'],
            // OJO: quitamos unique:users,email para permitir repetición en el archivo del MISMO colaborador
            'email'     => ['required', 'email:rfc,dns', 'max:150'],
            'direccion' => ['nullable', 'string', 'max:150'],
            'telefono'  => ['nullable', 'string', 'max:40'],
            'ciudad'    => ['nullable', 'string', 'max:100'],
            'sucursal'  => ['nullable', 'string', 'max:100'],
        ];
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
            $documento = trim((string)($row['documento'] ?? ''));
            $nombre    = trim((string)($row['nombre']    ?? ''));
            $email     = trim((string)($row['email']     ?? ''));
            $direccion = trim((string)($row['direccion'] ?? ''));
            $telefono  = trim((string)($row['telefono']  ?? ''));
            $ciudad    = trim((string)($row['ciudad']    ?? ''));
            $sucursal  = trim((string)($row['sucursal']  ?? 'ND'));

            if ($email === '') {
                DB::table('importerrors')->insert([
                    'row'        => $excelRow,
                    'attribute'  => 'email',
                    'errors'     => 'El email es obligatorio.',
                    'values'     => Str::limit(json_encode($row, JSON_UNESCAPED_UNICODE), 255, ''),
                    'created_at' => now(),
                ]);
                continue;
            }

            // --- Consolidación de email por DOCUMENTO ---
            if (isset($this->emailByDocumento[$documento])) {
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
            } else {
                $this->emailByDocumento[$documento] = $email;
            }

            DB::beginTransaction();
            try {
                // === 1) Upsert Colaborador (por documento) ===
                $col = Colaborador::updateOrCreate(
                    ['documento' => $documento],
                    [
                        'nombre'    => $nombre,
                        'email'     => $email, // consolidado
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
                        'updated_at'     => now(),
                        'created_at'     => now(),
                    ]
                );
                $this->stats['pivot']['upserts']++;

                // === 3) Usuario único por colaborador (login por email) ===
                $emailKey = mb_strtolower($email);
                $user = User::where('email', $email)->first();

                if (!$user) {
                    // Nuevo usuario para este email
                    $user = User::create([
                        'name'              => $nombre,
                        'email'             => $email,
                        'password'          => Hash::make($documento), // opcional: forzar cambio en primer login
                        'documento'         => $documento,
                        'email_verified_at' => now(),
                        'remember_token'    => Str::random(20),
                    ]);
                    $this->stats['users']['creados']++;
                } else {
                    // El email ya existe en users
                    if ((string)$user->documento === (string)$documento) {
                        // Es el MISMO colaborador → actualiza nombre (no resetea password)
                        $user->forceFill([
                            'name'      => $nombre,
                            'documento' => $documento,
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

                // === 5) Hijos (opcional) ===
                $children = $this->extractChildrenFromRow($row);
                foreach ($children as $child) {
                    $identificacion = (string)($child['identificacion'] ?: $documento);

                    if (trim((string)$child['nombre_hijo']) === '') {
                        $this->stats['hijos']['omitidos_sin_nombre']++;
                        continue;
                    }

                    ColaboradorHijo::updateOrCreate(
                        [
                            'identificacion' => $identificacion,
                            'nombre_hijo'    => (string)$child['nombre_hijo'],
                            'idcampaign'     => (int)$this->campaignId,
                        ],
                        [
                            'genero'     => $child['genero'] ? (string)$child['genero'] : null,
                            'rango_edad' => $child['rango_edad'] ? (string)$child['rango_edad'] : null,
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
        $lower = [];
        foreach ($row as $k => $v) {
            $key = strtolower(trim((string)$k));
            $key = preg_replace('/\s+/', '_', $key);
            $lower[$key] = is_string($v) ? trim($v) : $v;
        }

        $children = [];

        $hasDirect = !empty($lower['nombre_hijo'])
            || !empty($lower['genero'])
            || !empty($lower['sexo'])
            || !empty($lower['rango_edad'])
            || !empty($lower['rango']);

        if ($hasDirect) {
            $children[] = [
                'identificacion' => (string)($lower['hijo_identificacion'] ?? $lower['hijo_documento'] ?? $lower['identificacion'] ?? $lower['documento'] ?? ''),
                'nombre_hijo'    => (string)($lower['nombre_hijo'] ?? ''),
                'genero'         => (string)($lower['genero'] ?? $lower['sexo'] ?? ''),
                'rango_edad'     => (string)($lower['rango_edad'] ?? $lower['rango'] ?? ''),
            ];
        }

        $buckets = [];
        foreach ($lower as $key => $value) {
            if (preg_match('/^(nombre_hijo|genero|sexo|rango_edad|rango)_(\d+)$/', $key, $m)) {
                $fld = $m[1];
                $idx = (int)$m[2];
                $map = [
                    'nombre_hijo' => 'nombre_hijo',
                    'genero'      => 'genero',
                    'sexo'        => 'genero',
                    'rango_edad'  => 'rango_edad',
                    'rango'       => 'rango_edad',
                ];
                $buckets[$idx][$map[$fld]] = is_string($value) ? trim($value) : $value;
                continue;
            }

            if (preg_match('/^hijo_?(\d+)_(identificacion|documento|nombre|genero|sexo|rango(?:_edad)?)$/', $key, $m)) {
                $idx = (int)$m[1];
                $fld = $m[2];
                $map = [
                    'identificacion' => 'identificacion',
                    'documento'      => 'identificacion',
                    'nombre'         => 'nombre_hijo',
                    'genero'         => 'genero',
                    'sexo'           => 'genero',
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
                $c = array_merge(['identificacion' => '', 'nombre_hijo' => '', 'genero' => '', 'rango_edad' => ''], $data);
                if (($c['nombre_hijo'] ?? '') !== '') $children[] = $c;
            }
        }

        foreach ($children as &$c) {
            $c['identificacion'] = (string)($c['identificacion'] ?? '');
            $c['nombre_hijo']    = (string)($c['nombre_hijo'] ?? '');
            $c['genero']         = $c['genero'] === null ? '' : (string)$c['genero'];
            $c['rango_edad']     = $c['rango_edad'] === null ? '' : (string)$c['rango_edad'];
        }
        unset($c);

        return array_values(array_filter($children, fn($c) => $c['nombre_hijo'] !== ''));
    }
}
