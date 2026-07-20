<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MsGraphClient
{
    private string $clientId;
    private string $clientSecret;
    private string $tenant;
    private string $shareUrl;

    public function __construct()
    {
        $this->clientId     = config('services.msgraph.client_id');
        $this->clientSecret = config('services.msgraph.client_secret');
        $this->tenant       = config('services.msgraph.tenant_id');
        $this->shareUrl     = config('services.msgraph.share_url');
    }

    public function getToken(): string
    {
        $resp = Http::asForm()->post("https://login.microsoftonline.com/{$this->tenant}/oauth2/v2.0/token", [
            'grant_type'    => 'client_credentials',
            'client_id'     => $this->clientId,
            'client_secret' => $this->clientSecret,
            'scope'         => 'https://graph.microsoft.com/.default',
        ]);

        return $resp->json('access_token');
    }

    /**
     * 🔹 Lista recursivamente todos los hijos (archivos) de una carpeta compartida.
     */
    public function listAllSharedChildren(string $shareUrl, string $token): array
    {
        $encoded = rtrim(strtr(base64_encode($shareUrl), '+/', '-_'), '=');
        $url     = "https://graph.microsoft.com/v1.0/shares/u!{$encoded}/driveItem";

        $resp = Http::withToken($token)->get($url);
        $json = $resp->json();

        if (!$json || isset($json['error'])) {
            return [];
        }

        // Obtener el driveId desde parentReference o remoteItem
        $driveId = $json['parentReference']['driveId'] ?? null;
        if (!$driveId) {
            $driveId = $json['remoteItem']['parentReference']['driveId'] ?? null;
        }

        $items = [];

        // Si es carpeta → recorrer children
        if (isset($json['folder'])) {
            $childrenUrl = $url . '/children';
            $this->walkChildren($childrenUrl, $token, $driveId, $items);
        } else {
            // Si es archivo → guardar
            $items[] = [
                'name'        => $json['name'] ?? null,
                'downloadUrl' => $json['@microsoft.graph.downloadUrl'] ?? null,
            ];
        }

        return $items;
    }

    /**
     * 🔹 Recorre en profundidad un folder (recursivo)
     */
    private function walkFolder(string $itemUrl, string $token, ?string $driveId, array &$items): void
    {
        $resp = Http::withToken($token)->get($itemUrl);
        $json = $resp->json();

        if (!$json || isset($json['error'])) return;

        // Si es carpeta → recorrer children
        if (isset($json['folder'])) {
            $childrenUrl = $itemUrl . '/children';
            $this->walkChildren($childrenUrl, $token, $driveId, $items);
        } else {
            // Si es archivo → guardar
            $items[] = [
                'name'        => $json['name'] ?? null,
                'downloadUrl' => $json['@microsoft.graph.downloadUrl'] ?? null,
            ];
        }
    }

    private function walkChildren(string $childrenUrl, string $token, ?string $driveId, array &$items): void
    {
        $next = $childrenUrl;
        while ($next) {
            $resp = Http::withToken($token)->get($next);
            $json = $resp->json();

            if (!$json || isset($json['error'])) break;

            foreach ($json['value'] ?? [] as $child) {
                if (isset($child['folder'])) {
                    // 🔁 Subcarpeta → recursión usando el driveId correcto en lugar de /me
                    if ($driveId) {
                        $folderUrl = "https://graph.microsoft.com/v1.0/drives/{$driveId}/items/{$child['id']}";
                        $this->walkFolder($folderUrl, $token, $driveId, $items);
                    }
                } else {
                    // 📄 Archivo → guardar
                    $items[] = [
                        'name'        => $child['name'] ?? null,
                        'downloadUrl' => $child['@microsoft.graph.downloadUrl'] ?? null,
                    ];
                }
            }

            $next = $json['@odata.nextLink'] ?? null; // paginación
        }
    }

    /**
     * 🔹 Descarga archivo a partir del signedUrl
     */
    public function downloadBySignedUrl(string $signedUrl): string
    {
        return Http::get($signedUrl)->body();
    }
}
