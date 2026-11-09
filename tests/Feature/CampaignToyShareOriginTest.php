<?php

namespace Tests\Feature;

use App\Http\Controllers\CampaignToyController;
use App\Models\Campaign;
use App\Models\CampaignToy;
use App\Services\SharePointDownloader;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Verifica que la descarga de imágenes de referencias por juguete use únicamente
 * el share configurado en services.msgraph.share_url, aunque existan variables
 * SP_SITE_ID / SP_DRIVE_ID apuntando a otro drive.
 */
class CampaignToyShareOriginTest extends TestCase
{
    private const SHARE_URL = 'https://tenant-my.sharepoint.com/:f:/g/personal/share/ABC123?e=xyz';
    private const SHARE_DRIVE_ID = 'share-drive-id';
    private const SHARE_ROOT_ITEM_ID = 'share-root-item';
    private const OTHER_DRIVE_ID = 'otro-drive-id';
    private const OTHER_SITE_ID = 'otro-site-id';

    private string $tmpStorage;

    protected function setUp(): void
    {
        parent::setUp();

        // La descarga usa storage_path() con sink() (no pasa por Storage::fake),
        // así que se redirige el storage a una carpeta temporal para no tocar datos reales.
        $this->tmpStorage = sys_get_temp_dir() . '/share-origin-test-' . uniqid();
        mkdir($this->tmpStorage, 0775, true);
        $this->app->useStoragePath($this->tmpStorage);

        config([
            'database.default'                                    => 'sqlite',
            'database.connections.sqlite.driver'                  => 'sqlite',
            'database.connections.sqlite.database'                => ':memory:',
            'database.connections.sqlite.prefix'                  => '',
            'database.connections.sqlite.foreign_key_constraints' => false,
            'services.msgraph.share_url'                          => self::SHARE_URL,
            'services.msgraph.tenant_id'                          => 'tenant',
            'services.msgraph.client_id'                          => 'client',
            'services.msgraph.client_secret'                      => 'secret',
            // Configuración "contaminada" que NO debe usarse para descargar
            'sharepoint.site_id'                                  => self::OTHER_SITE_ID,
            'sharepoint.drive_id'                                 => self::OTHER_DRIVE_ID,
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::create('campaign_toys', function ($table) {
            $table->id();
            $table->unsignedBigInteger('idcampaign')->nullable();
            $table->string('referencia')->nullable();
            $table->string('combo')->nullable();
            $table->string('imagenppal')->nullable();
            $table->string('imgexists')->nullable();
            $table->timestamps();
        });

        Storage::fake('public');
    }

    protected function tearDown(): void
    {
        Schema::connection('sqlite')->dropIfExists('campaign_toys');
        DB::purge('sqlite');
        if (is_dir($this->tmpStorage)) {
            exec('rm -rf ' . escapeshellarg($this->tmpStorage));
        }
        parent::tearDown();
    }

    public function test_per_toy_fetch_downloads_only_from_configured_share(): void
    {
        $this->fakeGraph();

        // Espía: la ruta de servicio (otro drive) no debe invocarse nunca.
        $spy = new class extends SharePointDownloader {
            public bool $called = false;

            public function downloadToLocal(string $relativePath, string $destAbsPath): void
            {
                $this->called = true;
                throw new \RuntimeException('SharePointDownloader no debe usarse');
            }

            public function download(string $relativePath)
            {
                $this->called = true;
                return null;
            }
        };
        $this->app->instance(SharePointDownloader::class, $spy);

        $campaign = new Campaign();
        $campaign->id = 42;
        $toy = CampaignToy::create([
            'idcampaign' => 42,
            'referencia' => 'REF1',
            'combo'      => 'NC',
            'imagenppal' => 'REF1.jpg',
        ]);

        $request = Request::create('/', 'POST', ['sp_path' => 'REF1.jpg']);
        $response = app(CampaignToyController::class)
            ->fetchImageFromSharePoint($request, $campaign, $toy);

        $body = $response->getData(true);

        $this->assertTrue($body['ok']);
        $this->assertSame('graph-search', $body['results'][0]['source']);
        $this->assertFileExists($this->tmpStorage . '/app/public/campaign_toys/42/REF1.jpg');

        $this->assertFalse($spy->called, 'La ruta de servicio (otro drive/sitio) no debe usarse.');

        // Ninguna petición debe ir a otro drive ni a otro sitio.
        Http::assertNotSent(fn ($req) => str_contains($req->url(), self::OTHER_DRIVE_ID)
            || str_contains($req->url(), self::OTHER_SITE_ID)
            || str_contains($req->url(), '/sites/'));

        // Todas las consultas a Graph deben resolverse dentro del share configurado.
        Http::assertSent(fn ($req) => str_contains($req->url(), '/shares/'));
        Http::assertSent(fn ($req) => str_contains($req->url(), '/drives/' . self::SHARE_DRIVE_ID . '/'));
    }

    /**
     * Simula Microsoft Graph: el share resuelve a SHARE_DRIVE_ID y contiene REF1.jpg.
     * Cualquier otra URL devuelve 404 para que el test falle si se usa otro origen.
     */
    private function fakeGraph(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();

            if (str_contains($url, 'login.microsoftonline.com')) {
                return Http::response(['access_token' => 'token'], 200);
            }

            if (str_contains($url, '/shares/') && str_ends_with($url, '/driveItem')) {
                return Http::response([
                    'id'              => self::SHARE_ROOT_ITEM_ID,
                    'parentReference' => ['driveId' => self::SHARE_DRIVE_ID],
                ], 200);
            }

            if (str_contains($url, '/drives/' . self::SHARE_DRIVE_ID . '/items/' . self::SHARE_ROOT_ITEM_ID . '/search')) {
                return Http::response([
                    'value' => [[
                        'id'   => 'file-ref1',
                        'name' => 'REF1.jpg',
                        'file' => ['mimeType' => 'image/jpeg'],
                    ]],
                ], 200);
            }

            if (str_contains($url, '/drives/' . self::SHARE_DRIVE_ID . '/items/file-ref1/content')) {
                return Http::response('fake-image-bytes', 200);
            }

            return Http::response(['error' => ['code' => 'notFound']], 404);
        });
    }
}
