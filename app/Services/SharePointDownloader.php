<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class SharePointDownloader
{
    private Client $http;
    private string $tenantId;
    private string $clientId;
    private string $clientSecret;
    private string $siteId;
    private string $driveId;

    public function __construct()
    {
        $this->http         = new Client(['timeout' => 60]);
        $this->tenantId     = (string) config('services.msgraph.tenant_id');
        $this->clientId     = (string) config('services.msgraph.client_id');
        $this->clientSecret = (string) config('services.msgraph.client_secret');
        $this->siteId       = (string) config('services.msgraph.sp_site_id');
        $this->driveId      = (string) config('services.msgraph.sp_drive_id');
    }

    /**
     * Descarga un archivo desde el drive de SharePoint a una ruta local absoluta.
     * @param string $relativePath  Ej: "juguetes/ABC123.jpg"
     * @param string $destAbsPath   Ruta local absoluta (filesystem)
     * @throws \RuntimeException
     */
    public function downloadToLocal(string $relativePath, string $destAbsPath): void
    {
        $token = $this->getAccessToken();
        // endpoint de contenido directo:
        // GET /sites/{siteId}/drives/{driveId}/root:/relativePath:/content
        $url = "https://graph.microsoft.com/v1.0/sites/{$this->siteId}/drives/{$this->driveId}/root:/" . ltrim($relativePath, '/') . ":/content";

        try {
            $resp = $this->http->get($url, [
                'headers' => [
                    'Authorization' => "Bearer {$token}",
                ],
                'stream' => true,
            ]);

            if ($resp->getStatusCode() !== 200) {
                throw new \RuntimeException("Graph devolvió {$resp->getStatusCode()}");
            }

            // Escribe stream a disco
            $body = $resp->getBody();
            $dir = dirname($destAbsPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            $fp = @fopen($destAbsPath, 'wb');
            if (!$fp) {
                throw new \RuntimeException("No se pudo escribir en {$destAbsPath}");
            }
            while (!$body->eof()) {
                fwrite($fp, $body->read(8192));
            }
            fclose($fp);
        } catch (GuzzleException $e) {
            throw new \RuntimeException("Error HTTP al descargar: " . $e->getMessage());
        }
    }

    private function getAccessToken(): string
    {
        $tokenUrl = "https://login.microsoftonline.com/{$this->tenantId}/oauth2/v2.0/token";

        try {
            $resp = $this->http->post($tokenUrl, [
                'form_params' => [
                    'client_id'     => $this->clientId,
                    'client_secret' => $this->clientSecret,
                    'scope'         => 'https://graph.microsoft.com/.default',
                    'grant_type'    => 'client_credentials',
                ],
            ]);
            $data = json_decode((string) $resp->getBody(), true);
            if (!isset($data['access_token'])) {
                throw new \RuntimeException('No se obtuvo access_token');
            }
            return $data['access_token'];
        } catch (GuzzleException $e) {
            throw new \RuntimeException("No se pudo obtener token: " . $e->getMessage());
        }
    }
}
