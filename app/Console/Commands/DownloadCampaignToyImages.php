<?php

namespace App\Console\Commands;

use App\Models\Campaign;
use App\Models\CampaignToy;
use App\Services\MsGraphClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Throwable;

class DownloadCampaignToyImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaign:download-images 
                            {campaignId? : El ID de la campaña a procesar} 
                            {--all : Descargar imágenes para todas las campañas existentes} 
                            {--ref=* : Descargar solo estas referencias (ej. --ref=REF1 --ref=REF2)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Descarga las imágenes de juguetes de campaña utilizando Microsoft Graph/SharePoint';

    /**
     * Execute the console command.
     */
    public function handle(MsGraphClient $graph): int
    {
        $campaignId = $this->argument('campaignId');
        $all = $this->option('all');
        $onlyRefs = $this->option('ref');

        if ($all) {
            $campaigns = Campaign::all();
            if ($campaigns->isEmpty()) {
                $this->error('No se encontraron campañas en la base de datos.');
                return self::FAILURE;
            }
        } elseif ($campaignId) {
            $campaign = Campaign::find($campaignId);
            if (!$campaign) {
                $this->error("La campaña con ID {$campaignId} no existe.");
                return self::FAILURE;
            }
            $campaigns = collect([$campaign]);
        } else {
            // Modo interactivo
            $campaignsList = Campaign::orderBy('id', 'desc')->get();
            if ($campaignsList->isEmpty()) {
                $this->error('No hay campañas registradas para seleccionar.');
                return self::FAILURE;
            }

            $choices = $campaignsList->mapWithKeys(function ($c) {
                return [$c->id => "[ID: {$c->id}] {$c->nombre}"];
            })->toArray();

            $selectedName = $this->choice(
                'Seleccione la campaña para descargar las imágenes:',
                array_values($choices)
            );

            // Obtener el ID del string seleccionado
            $selectedId = array_search($selectedName, $choices);
            $campaign = Campaign::find($selectedId);
            $campaigns = collect([$campaign]);
        }

        $this->info('Iniciando proceso de descarga con Microsoft Graph...');

        try {
            $this->comment('Obteniendo token de acceso...');
            $token = $graph->getToken();

            $shareUrl = config('services.msgraph.share_url');
            if (empty($shareUrl)) {
                $this->error('La variable MSGRAPH_SHARE_URL no está configurada en el archivo .env o config/services.php.');
                return self::FAILURE;
            }

            $this->comment('Listando archivos del sitio de SharePoint/OneDrive...');
            $files = $graph->listAllSharedChildren($shareUrl, $token);
            
            $this->info('Se encontraron ' . count($files) . ' archivos en SharePoint.');

            // Indexar archivos (nombre en minúsculas -> downloadUrl)
            $index = [];
            foreach ($files as $f) {
                $index[mb_strtolower(trim($f['name']))] = $f['downloadUrl'];
            }

            foreach ($campaigns as $campaign) {
                $this->info("--------------------------------------------------");
                $this->info("Procesando campaña: [ID: {$campaign->id}] {$campaign->nombre}");

                $query = CampaignToy::where('idcampaign', $campaign->id);
                if (!empty($onlyRefs)) {
                    $query->whereIn('referencia', $onlyRefs);
                }
                $toys = $query->get(['id', 'combo', 'imagenppal', 'referencia']);

                if ($toys->isEmpty()) {
                    $this->warn('No se encontraron juguetes asociados a esta campaña en la base de datos.');
                    continue;
                }

                $this->info('Total de juguetes a validar en la base de datos: ' . $toys->count());

                $okCount = 0;
                $failCount = 0;
                $skippedCount = 0;
                $errorsList = [];
                $totalToys = $toys->count();

                foreach ($toys as $indexToy => $toy) {
                    $current = $indexToy + 1;
                    $ref = $toy->referencia;
                    $prefix = sprintf("[%d/%d]", $current, $totalToys);
                    $img = trim((string) $toy->imagenppal);

                    if ($img === '') {
                        $toy->update(['imgexists' => 'N']);
                        $skippedCount++;
                        $this->line("{$prefix} <comment>[OMITIDO]</comment> Referencia: <comment>{$ref}</comment> (Sin imagen asignada en base de datos).");
                        continue;
                    }

                    // Si es combo, las imágenes se separan por '+'
                    $names = ($toy->combo === 'COM')
                        ? array_filter(array_map('trim', explode('+', $img)))
                        : [$img];

                    $allOk = true;
                    $anyOk = false;
                    $downloadedNames = [];
                    $toyErrors = [];

                    foreach ($names as $name) {
                        $key = mb_strtolower($name);

                        if (!isset($index[$key])) {
                            Log::warning('[Artisan Cmd] Imagen no encontrada en SharePoint', [
                                'campaign_id' => $campaign->id,
                                'toy_id' => $toy->id,
                                'file' => $name
                            ]);
                            $allOk = false;
                            $failCount++;
                            $toyErrors[] = "'{$name}' no encontrada en SharePoint";
                            $errorsList[] = [
                                'ref' => $ref,
                                'file' => $name,
                                'reason' => 'Archivo no disponible en el directorio de SharePoint.'
                            ];
                            continue;
                        }

                        try {
                            $bin = $graph->downloadBySignedUrl($index[$key]);
                            
                            // Estructura esperada por la vista product: campaign_toys/{idCampaign}/{filename}
                            $path = "campaign_toys/{$campaign->id}/{$name}";
                            
                            Storage::disk('public')->put($path, $bin);

                            $anyOk = true;
                            $okCount++;
                            $downloadedNames[] = $name;
                        } catch (Throwable $e) {
                            Log::error('[Artisan Cmd] Error descargando imagen desde SharePoint', [
                                'campaign_id' => $campaign->id,
                                'toy_id' => $toy->id,
                                'file' => $name,
                                'error' => $e->getMessage()
                            ]);
                            $allOk = false;
                            $failCount++;
                            $toyErrors[] = "'{$name}': " . $e->getMessage();
                            $errorsList[] = [
                                'ref' => $ref,
                                'file' => $name,
                                'reason' => 'Error de red/descarga: ' . $e->getMessage()
                            ];
                        }
                    }

                    // Actualizar estado imgexists en base de datos
                    // Regla:
                    // - Normal/No combo: S si al menos una se descargó con éxito (anyOk)
                    // - Combo: S solo si todas las partes del combo se descargaron con éxito (allOk)
                    $markS = $toy->combo === 'COM' ? (count($names) > 0 && $allOk) : $anyOk;
                    $toy->update(['imgexists' => $markS ? 'S' : 'N']);

                    if (empty($toyErrors)) {
                        $this->line("{$prefix} <info>[OK]</info> Referencia: <info>{$ref}</info> - Descargadas: " . implode(', ', $downloadedNames));
                    } else {
                        $msgErr = implode(' | ', $toyErrors);
                        $this->line("{$prefix} <error>[ERROR]</error> Referencia: <error>{$ref}</error> - Fallos: <error>{$msgErr}</error>");
                    }
                }

                $this->newLine();
                $this->info("Resumen de campaña [ID: {$campaign->id}] {$campaign->nombre}:");
                $this->line("  - Descargadas exitosamente: <info>{$okCount}</info>");
                $this->line("  - Errores/No encontradas: <error>{$failCount}</error>");
                $this->line("  - Omitidas (sin imagen asignada): <comment>{$skippedCount}</comment>");

                if (!empty($errorsList)) {
                    $this->newLine();
                    $this->warn("Listado detallado de fallos/errores en esta campaña:");
                    $this->table(
                        ['Referencia', 'Archivo / Imagen', 'Detalle del Error'],
                        $errorsList
                    );
                }
            }

            $this->newLine();
            $this->info('¡Proceso completado exitosamente!');
            return self::SUCCESS;

        } catch (Throwable $e) {
            $this->newLine();
            $this->error('Ocurrió un error general durante el proceso: ' . $e->getMessage());
            Log::error('[Artisan Cmd] Error general descargando imágenes de campaña', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return self::FAILURE;
        }
    }
}
