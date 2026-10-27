<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class PublicStorageUrl
{
    /**
     * URL absoluta de una ruta del disco 'public', o null si no está
     * configurada o el archivo no existe. Las URL http(s) se devuelven tal
     * cual, porque no es posible verificarlas desde aquí.
     */
    public static function ifExists(?string $path): ?string
    {
        $path = trim((string) $path);
        if ($path === '' || $path === 'ND') {
            return null;
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $path = ltrim(str_replace('\\', '/', $path), '/');
        $path = preg_replace('#^(storage/app/public/|app/public/|public/|storage/)#i', '', $path);

        return Storage::disk('public')->exists($path)
            ? url(Storage::disk('public')->url($path))
            : null;
    }
}
