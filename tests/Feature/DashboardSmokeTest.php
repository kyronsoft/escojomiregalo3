<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Humo del dashboard: detecta variables indefinidas y consultas rotas tras cambiar
 * la lógica de vigencia de campañas. Usa SQLite en memoria con el esquema mínimo.
 */
class DashboardSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default'                                    => 'sqlite',
            'database.connections.sqlite.driver'                  => 'sqlite',
            'database.connections.sqlite.database'                => ':memory:',
            'database.connections.sqlite.prefix'                  => '',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'permission.cache.expiration_time'                    => 0,
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

    public function test_admin_puede_abrir_el_dashboard_con_campanas_vigentes_y_no_vigentes(): void
    {
        $admin = User::create([
            'name' => 'Admin',
            'email' => 'admin@test.local',
            'password' => bcrypt('secret'),
        ]);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        $admin->assignRole('Admin');

        $now = now();

        // Vigente: empezó ayer y termina mañana.
        DB::table('campaigns')->insert([
            'id' => 1, 'nit' => '900', 'nombre' => 'Vigente', 'idtipo' => 1, 'dashboard' => 1,
            'fechaini' => $now->copy()->subDay(), 'fechafin' => $now->copy()->addDay(),
            'created_at' => $now, 'updated_at' => $now,
        ]);

        // No vigente: terminó ayer.
        DB::table('campaigns')->insert([
            'id' => 2, 'nit' => '900', 'nombre' => 'Vencida', 'idtipo' => 1, 'dashboard' => 1,
            'fechaini' => $now->copy()->subDays(5), 'fechafin' => $now->copy()->subDay(),
            'created_at' => $now, 'updated_at' => $now,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard.index'))
            ->assertOk();
    }

    public function test_fechas_de_campana_se_muestran_en_gmt_menos_5(): void
    {
        // 04:59 UTC del 2 de noviembre equivale a 23:59 del 1 de noviembre en Bogotá.
        $campaign = new Campaign([
            'fechaini' => '2026-11-01 13:00:00',
            'fechafin' => '2026-11-02 04:59:00',
        ]);

        $this->assertSame('2026-11-01 08:00', $campaign->fechainiLocal()->format('Y-m-d H:i'));
        $this->assertSame('2026-11-01 23:59', $campaign->fechafinLocal()->format('Y-m-d H:i'));
    }

    private function createSchema(): void
    {
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
            $t->primary(['permission_id', 'model_id', 'model_type']);
        });

        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id');
            $t->string('model_type');
            $t->unsignedBigInteger('model_id');
            $t->primary(['role_id', 'model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->unsignedBigInteger('permission_id');
            $t->unsignedBigInteger('role_id');
            $t->primary(['permission_id', 'role_id']);
        });

        Schema::create('users', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('email')->unique();
            $t->string('password');
            $t->string('nit', 10)->nullable();
            $t->rememberToken();
            $t->timestamps();
        });

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

        Schema::create('seleccionados', function (Blueprint $t) {
            $t->id();
            $t->string('documento', 15);
            $t->unsignedBigInteger('idcampaing');
            $t->unsignedBigInteger('idhijo');
            $t->string('referencia', 100);
            $t->char('selected', 1)->default('N');
            $t->timestamps();
        });

        Schema::create('campaign_toys', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('idcampaign');
            $t->string('referencia', 100)->nullable();
            $t->string('nombre', 100)->nullable();
            $t->timestamps();
        });
    }
}
