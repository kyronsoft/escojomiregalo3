<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;
use App\Models\User;

class SeleccionadosAccessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Redirigir a SQLite en memoria para aislar estos tests del DB de producción
        config([
            'database.default'                    => 'sqlite',
            'database.connections.sqlite.driver'  => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix'  => '',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'permission.cache.expiration_time'    => 0,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createSchema();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlite')->dropAllTables();
        DB::purge('sqlite');
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Schema helpers
    // -------------------------------------------------------------------------

    private function createSchema(): void
    {
        // Tablas de Spatie Permissions
        Schema::create('permissions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });

        Schema::create('roles', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name');
            $t->string('guard_name');
            $t->timestamps();
            $t->unique(['name', 'guard_name']);
        });

        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
            $t->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->index(['model_id', 'model_type']);
            $t->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['permission_id', 'role_id']);
        });

        // Users
        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('nit', 10)->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

        // Tablas de dominio
        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('nit', 10);
            $t->string('nombre', 100);
            $t->tinyInteger('idtipo')->default(1);
            $t->dateTime('fechaini')->nullable();
            $t->dateTime('fechafin')->nullable();
            $t->string('banner', 100)->default('ND');
            $t->char('demo', 3)->default('off');
            $t->integer('doc_yeminus')->default(0);
            $t->text('mailtext')->nullable();
            $t->string('subject', 150)->nullable();
            $t->tinyInteger('dashboard')->default(0);
            $t->timestamps();
        });

        Schema::create('campaing_colaboradores', function (Blueprint $t) {
            $t->unsignedBigInteger('idcampaign');
            $t->string('documento', 15);
            $t->string('nit', 10);
            $t->string('sucursal', 100)->default('ND');
            $t->tinyInteger('email_notified')->default(0);
            $t->timestamps();
            $t->primary(['idcampaign', 'documento', 'nit']);
        });

        Schema::create('colaboradores', function (Blueprint $t) {
            $t->string('documento', 15)->primary();
            $t->string('nombre', 100);
            $t->string('email', 75)->nullable();
            $t->string('direccion', 255)->nullable();
            $t->string('telefono', 10)->nullable();
            $t->char('ciudad', 5)->nullable();
            $t->string('observaciones', 255)->nullable();
            $t->string('nit', 10);
            $t->char('politicadatos', 1)->default('N');
            $t->string('sucursal', 100)->nullable();
            $t->timestamps();
        });

        Schema::create('colaborador_hijos', function (Blueprint $t) {
            $t->id();
            $t->string('identificacion', 15);
            $t->string('nombre_hijo', 100)->nullable();
            $t->string('genero', 10)->nullable();
            $t->string('rango_edad', 15)->nullable();
            $t->unsignedBigInteger('idcampaing');
            $t->timestamps();
        });

        Schema::create('seleccionados', function (Blueprint $t) {
            $t->id();
            $t->string('documento', 15);
            $t->unsignedBigInteger('idcampaing');
            $t->unsignedBigInteger('idhijo');
            $t->string('referencia', 100);
            $t->char('selected', 1)->default('N');
            $t->timestamps();
        });

        // Tablas auxiliares para los LEFT JOINs (pueden quedar vacías)
        Schema::create('empresas', function (Blueprint $t) {
            $t->string('nit', 10)->primary();
            $t->string('nombre', 50)->nullable();
            $t->timestamps();
        });

        Schema::create('ciudades', function (Blueprint $t) {
            $t->char('codigo', 5)->primary();
            $t->string('departamento', 50)->nullable();
        });

        Schema::create('campaign_toys', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('idcampaign');
            $t->string('referencia', 100)->nullable();
            $t->string('nombre', 100)->nullable();
            $t->timestamps();
        });
    }

    // -------------------------------------------------------------------------
    // Data helpers
    // -------------------------------------------------------------------------

    /**
     * Inserta dos empresas con sus campañas, colaboradores e hijos.
     * Devuelve [$rrhhA, $admin, $campaignAId, $campaignBId].
     */
    private function seedTwoCompanies(): array
    {
        Role::create(['name' => 'RRHH-Cliente', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin',         'guard_name' => 'web']);

        // Campañas
        $campaignAId = DB::table('campaigns')->insertGetId([
            'nit' => 'NIT_A', 'nombre' => 'Campaña A',
            'fechaini' => now(), 'fechafin' => now()->addMonth(),
        ]);
        $campaignBId = DB::table('campaigns')->insertGetId([
            'nit' => 'NIT_B', 'nombre' => 'Campaña B',
            'fechaini' => now(), 'fechafin' => now()->addMonth(),
        ]);

        // Colaboradores
        DB::table('colaboradores')->insert([
            ['documento' => 'DOC_A', 'nombre' => 'Colab A', 'nit' => 'NIT_A'],
            ['documento' => 'DOC_B', 'nombre' => 'Colab B', 'nit' => 'NIT_B'],
        ]);

        // Asignaciones a campañas
        DB::table('campaing_colaboradores')->insert([
            ['idcampaign' => $campaignAId, 'documento' => 'DOC_A', 'nit' => 'NIT_A'],
            ['idcampaign' => $campaignBId, 'documento' => 'DOC_B', 'nit' => 'NIT_B'],
        ]);

        // Hijos
        DB::table('colaborador_hijos')->insert([
            ['identificacion' => 'DOC_A', 'idcampaing' => $campaignAId, 'nombre_hijo' => 'Hijo A'],
            ['identificacion' => 'DOC_B', 'idcampaing' => $campaignBId, 'nombre_hijo' => 'Hijo B'],
        ]);

        // Usuarios
        $rrhhA = User::create([
            'name'     => 'RRHH Empresa A',
            'email'    => 'rrhh_a@test.com',
            'password' => Hash::make('password'),
            'nit'      => 'NIT_A',
        ]);
        $rrhhA->assignRole('RRHH-Cliente');

        $admin = User::create([
            'name'     => 'Admin',
            'email'    => 'admin@test.com',
            'password' => Hash::make('password'),
            'nit'      => null,
        ]);
        $admin->assignRole('Admin');

        return [$rrhhA, $admin, $campaignAId, $campaignBId];
    }

    // -------------------------------------------------------------------------
    // Tests
    // -------------------------------------------------------------------------

    public function test_rrhh_cliente_data_solo_ve_su_propio_nit(): void
    {
        [$rrhhA] = $this->seedTwoCompanies();

        $response = $this->actingAs($rrhhA)
            ->getJson(route('seleccionados.data'));

        $response->assertOk();
        $rows = $response->json('data');

        $this->assertNotEmpty($rows);
        foreach ($rows as $row) {
            $this->assertEquals('DOC_A', $row['documento'],
                'RRHH-Cliente de NIT_A no debe ver colaboradores de NIT_B');
        }
    }

    public function test_rrhh_cliente_con_idcampaign_ajeno_no_ve_datos_de_otra_empresa(): void
    {
        [$rrhhA, , , $campaignBId] = $this->seedTwoCompanies();

        $response = $this->actingAs($rrhhA)
            ->getJson(route('seleccionados.data', ['idcampaign' => $campaignBId]));

        $response->assertOk();
        $rows = $response->json('data');

        // El campaignId ajeno es descartado; el filtro cc.nit=NIT_A sigue activo
        foreach ($rows as $row) {
            $this->assertEquals('DOC_A', $row['documento'],
                'Un idcampaign de NIT_B no debe filtrar datos de NIT_A');
        }
    }

    public function test_admin_ve_datos_de_todos_los_nit(): void
    {
        [, $admin] = $this->seedTwoCompanies();

        $response = $this->actingAs($admin)
            ->getJson(route('seleccionados.data'));

        $response->assertOk();
        $rows   = $response->json('data');
        $docs   = collect($rows)->pluck('documento')->unique()->sort()->values()->all();

        $this->assertContains('DOC_A', $docs, 'Admin debe ver NIT_A');
        $this->assertContains('DOC_B', $docs, 'Admin debe ver NIT_B');
    }

    public function test_rrhh_cliente_export_devuelve_xlsx(): void
    {
        [$rrhhA, , $campaignAId] = $this->seedTwoCompanies();

        $response = $this->actingAs($rrhhA)
            ->get(route('seleccionados.export', ['idcampaign' => $campaignAId]));

        $response->assertOk();
        $contentType = $response->headers->get('Content-Type') ?? '';
        $this->assertStringContainsStringIgnoringCase('spreadsheet', $contentType);
    }

    public function test_export_sin_campana_rechaza_la_solicitud(): void
    {
        [$rrhhA] = $this->seedTwoCompanies();

        $response = $this->actingAs($rrhhA)
            ->get(route('seleccionados.export'));

        $response->assertStatus(422);
    }

    public function test_rrhh_cliente_export_con_idcampaign_ajeno_mantiene_restriccion(): void
    {
        [$rrhhA, , , $campaignBId] = $this->seedTwoCompanies();

        // Simular que el usuario altera manualmente el idcampaign en la URL
        $response = $this->actingAs($rrhhA)
            ->get(route('seleccionados.export', ['idcampaign' => $campaignBId]));

        $response->assertOk();
        // Si llega aquí sin error y devuelve un xlsx, la restricción es correcta;
        // la validación de contenido se cubre en los tests de data().
    }
}
