<?php

namespace Tests\Feature;

use App\Exports\CampaignToysExport;
use App\Models\Campaign;
use App\Models\CampaignToy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use ZipArchive;

class CampaignToysExportTest extends TestCase
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

        $this->createSchema();
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlite')->dropAllTables();
        DB::purge('sqlite');
        parent::tearDown();
    }

    private function createSchema(): void
    {
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

    private function seedCampaignWithImage(): Campaign
    {
        Storage::fake('public');

        $campaign = Campaign::create([
            'nit' => '1234567890',
            'nombre' => 'Campaña Demo',
        ]);

        $imagePath = "campaign_toys/{$campaign->id}/ref-001.png";
        Storage::disk('public')->put($imagePath, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO6W3n0AAAAASUVORK5CYII='
        ));

        CampaignToy::create([
            'idcampaign' => $campaign->id,
            'referencia' => 'REF-001',
            'nombre' => 'Juguete Demo',
            'imagenppal' => 'ref-001.png',
            'genero' => 'UNISEX',
            'unidades' => 5,
            'precio_unitario' => 1000,
            'porcentaje' => '10',
            'descripcion' => 'Producto de prueba',
        ]);

        return $campaign;
    }

    public function test_export_route_downloads_references_file_for_specific_campaign(): void
    {
        $campaign = $this->seedCampaignWithImage();

        $response = $this->get(route('campaigns.toys.export', $campaign));

        $response->assertOk();
        $contentDisposition = $response->headers->get('content-disposition') ?? '';

        $this->assertStringContainsString('referencias_campana_' . $campaign->id, $contentDisposition);
        $this->assertStringContainsStringIgnoringCase('spreadsheet', $response->headers->get('content-type') ?? '');
    }

    public function test_export_xlsx_embeds_reference_image(): void
    {
        $campaign = $this->seedCampaignWithImage();

        Storage::fake('local');
        Excel::store(new CampaignToysExport($campaign->id), 'campaign-toys.xlsx', 'local');

        $xlsxPath = Storage::disk('local')->path('campaign-toys.xlsx');
        $zip = new ZipArchive();

        $this->assertTrue($zip->open($xlsxPath) === true, 'No se pudo abrir el XLSX generado.');

        $drawingXml = $zip->getFromName('xl/drawings/drawing1.xml');
        $this->assertNotFalse($drawingXml, 'El archivo no contiene el drawing con la imagen.');

        $mediaFiles = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            $mediaFiles[] = $stat['name'];
        }

        $this->assertTrue(
            collect($mediaFiles)->contains(fn($name) => str_starts_with($name, 'xl/media/')),
            'El XLSX no incrustó ninguna imagen en media.'
        );

        $zip->close();
    }
}
