<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Campaign;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/**
 * Pruebas de seguridad para SeleccionadosController:
 * - RRHH-Cliente solo ve/exporta campañas de su propio NIT.
 * - Export sin campaña devuelve 422.
 * - Export con campaña de otro NIT devuelve 403.
 */
class SeleccionadosAccessTest extends TestCase
{
    use DatabaseTransactions;

    private string $nitA = 'TEST001';
    private string $nitB = 'TEST002';

    protected function setUp(): void
    {
        parent::setUp();

        // Asegurar que el rol exista
        Role::firstOrCreate(['name' => 'RRHH-Cliente', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    }

    private function makeUser(string $nit, string $role): User
    {
        $user = User::forceCreate([
            'name'     => "Test {$role} {$nit}",
            'email'    => "test_{$role}_{$nit}_" . uniqid() . '@test.local',
            'password' => bcrypt('secret'),
            'nit'      => $nit,
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function makeCampaign(string $nit, string $suffix = ''): Campaign
    {
        return Campaign::forceCreate([
            'nit'       => $nit,
            'nombre'    => "Campaña Test {$nit}{$suffix}",
            'idtipo'    => 1,
            'fechaini'  => now()->subDay(),
            'fechafin'  => now()->addDays(30),
            'banner'    => 'ND',
            'demo'      => 'off',
            'doc_yeminus' => 0,
            'mailtext'  => 'Hola',
            'subject'   => 'Test',
            'dashboard' => 0,
        ]);
    }

    /** RRHH-Cliente no puede exportar sin indicar campaña */
    public function test_export_sin_campana_devuelve_422(): void
    {
        $user = $this->makeUser($this->nitA, 'RRHH-Cliente');

        $response = $this->actingAs($user)
            ->get(route('seleccionados.export'));

        $response->assertStatus(422);
    }

    /** RRHH-Cliente no puede exportar una campaña de otro NIT */
    public function test_rrhh_no_puede_exportar_campana_de_otro_nit(): void
    {
        $userA     = $this->makeUser($this->nitA, 'RRHH-Cliente');
        $campaignB = $this->makeCampaign($this->nitB);

        $response = $this->actingAs($userA)
            ->get(route('seleccionados.export', ['idcampaign' => $campaignB->id]));

        $response->assertStatus(403);
    }

    /** RRHH-Cliente puede exportar su propia campaña (200 o descarga) */
    public function test_rrhh_puede_exportar_su_propia_campana(): void
    {
        $userA     = $this->makeUser($this->nitA, 'RRHH-Cliente');
        $campaignA = $this->makeCampaign($this->nitA);

        $response = $this->actingAs($userA)
            ->get(route('seleccionados.export', ['idcampaign' => $campaignA->id]));

        // La descarga devuelve 200 con content-type Excel
        $response->assertStatus(200);
        $this->assertStringContainsString(
            'spreadsheet',
            strtolower($response->headers->get('Content-Type') ?? '')
        );
    }

    /** Admin puede exportar cualquier campaña */
    public function test_admin_puede_exportar_campana_de_cualquier_nit(): void
    {
        $admin     = $this->makeUser($this->nitA, 'Admin');
        $campaignB = $this->makeCampaign($this->nitB);

        $response = $this->actingAs($admin)
            ->get(route('seleccionados.export', ['idcampaign' => $campaignB->id]));

        $response->assertStatus(200);
    }

    /** El endpoint data() del RRHH-Cliente no expone registros de otro NIT */
    public function test_data_de_rrhh_no_incluye_colaboradores_de_otro_nit(): void
    {
        $userA     = $this->makeUser($this->nitA, 'RRHH-Cliente');
        $campaignB = $this->makeCampaign($this->nitB, '_B');

        // Insertar un colaborador asignado a campaignB con nitB
        DB::table('campaing_colaboradores')->insert([
            'idcampaign' => $campaignB->id,
            'documento'  => '99999999',
            'nit'        => $this->nitB,
            'sucursal'   => 'ND',
        ]);

        $response = $this->actingAs($userA)
            ->getJson(route('seleccionados.data', ['idcampaign' => $campaignB->id]));

        $response->assertStatus(200);
        // El resultado no debe contener registros del nitB
        $data = $response->json('data') ?? [];
        $this->assertEmpty($data, 'RRHH-Cliente no debe ver colaboradores de otro NIT');
    }
}
