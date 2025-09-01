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

        $items = [];

        // Inicia en el folder compartido raíz
        $this->walkFolder($url, $token, $items);

        return $items;
    }

    /**
     * 🔹 Recorre en profundidad un folder (recursivo)
     */
    private function walkFolder(string $itemUrl, string $token, array &$items): void
    {
        $resp = Http::withToken($token)->get($itemUrl);
        $json = $resp->json();

        if (!$json) return;

        // Si es carpeta → recorrer children
        if (isset($json['folder'])) {
            $childrenUrl = $itemUrl . '/children';
            $this->walkChildren($childrenUrl, $token, $items);
        } else {
            // Si es archivo → guardar
            $items[] = [
                'name'        => $json['name'],
                'downloadUrl' => $json['@microsoft.graph.downloadUrl'] ?? null,
            ];
        }
    }

    private function walkChildren(string $childrenUrl, string $token, array &$items): void
    {
        $next = $childrenUrl;
        while ($next) {
            $resp = Http::withToken($token)->get($next);
            $json = $resp->json();

            foreach ($json['value'] ?? [] as $child) {
                if (isset($child['folder'])) {
                    // 🔁 Subcarpeta → recursión
                    $this->walkFolder("https://graph.microsoft.com/v1.0/me/drive/items/{$child['id']}", $token, $items);
                } else {
                    // 📄 Archivo → guardar
                    $items[] = [
                        'name'        => $child['name'],
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
