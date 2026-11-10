<?php

namespace Tests\Feature;

use App\Models\Campaign;
use App\Models\CampaignToy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CampaignToyEdadesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.driver' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.prefix' => '',
            'database.connections.sqlite.foreign_key_constraints' => false,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('campaigns', function (Blueprint $t) {
            $t->id();
            $t->string('nit', 10)->nullable();
            $t->string('nombre', 100);
            $t->timestamps();
        });

        Schema::create('campaign_toys', function (Blueprint $t) {
            $t->id();
            $t->char('combo', 3)->default('NC');
            $t->unsignedBigInteger('idcampaign');
            $t->string('referencia', 100);
            $t->text('nombre');
            $t->string('imagenppal', 100)->nullable();
            $t->string('genero', 10)->default('UNISEX');
            $t->string('desde', 3)->default('0');
            $t->string('hasta', 10)->default('0');
            $t->integer('unidades')->default(0);
            $t->integer('precio_unitario')->default(0);
            $t->string('porcentaje', 100)->default('0');
            $t->integer('seleccionadas')->default(0);
            $t->char('imgexists', 1)->default('N');
            $t->text('descripcion')->nullable();
            $t->integer('escogidos')->default(0);
            $t->string('idoriginal', 15)->default('0');
            $t->timestamps();
        });

    }

    protected function tearDown(): void
    {
        Schema::connection('sqlite')->dropAllTables();
        DB::purge('sqlite');
        parent::tearDown();
    }

    private function seedToy(): array
    {
        $campaign = Campaign::create(['nit' => '1234567890', 'nombre' => 'Campaña Demo']);

        $toy = CampaignToy::create([
            'idcampaign' => $campaign->id,
            'referencia' => 'REF-001',
            'nombre' => 'Juguete Demo',
            'genero' => 'UNISEX',
            'desde' => '10',
            'hasta' => '13',
        ]);

        return [$campaign, $toy];
    }

    public function test_actualiza_edades_de_un_registro(): void
    {
        [$campaign, $toy] = $this->seedToy();

        $response = $this->patchJson(
            route('campaigns.toys.edades', ['campaign' => $campaign->id, 'toy' => $toy->id]),
            ['desde' => 8, 'hasta' => 12]
        );

        $response->assertOk()->assertJson(['ok' => true, 'desde' => 8, 'hasta' => 12]);

        $toy->refresh();
        $this->assertSame('8', (string) $toy->desde);
        $this->assertSame('12', (string) $toy->hasta);
    }

    public function test_rechaza_hasta_menor_que_desde(): void
    {
        [$campaign, $toy] = $this->seedToy();

        $response = $this->patchJson(
            route('campaigns.toys.edades', ['campaign' => $campaign->id, 'toy' => $toy->id]),
            ['desde' => 12, 'hasta' => 8]
        );

        $response->assertStatus(422)->assertJson(['ok' => false]);

        $toy->refresh();
        $this->assertSame('10', (string) $toy->desde);
        $this->assertSame('13', (string) $toy->hasta);
    }

    public function test_rechaza_edad_fuera_de_rango(): void
    {
        [$campaign, $toy] = $this->seedToy();

        $response = $this->patchJson(
            route('campaigns.toys.edades', ['campaign' => $campaign->id, 'toy' => $toy->id]),
            ['desde' => -1, 'hasta' => 121]
        );

        $response->assertStatus(422)->assertJson(['ok' => false]);
    }

    public function test_no_actualiza_juguete_de_otra_campana(): void
    {
        [, $toy] = $this->seedToy();
        $otra = Campaign::create(['nit' => '999', 'nombre' => 'Otra']);

        $this->patchJson(
            route('campaigns.toys.edades', ['campaign' => $otra->id, 'toy' => $toy->id]),
            ['desde' => 5, 'hasta' => 7]
        )->assertNotFound();

        $toy->refresh();
        $this->assertSame('10', (string) $toy->desde);
    }
}
