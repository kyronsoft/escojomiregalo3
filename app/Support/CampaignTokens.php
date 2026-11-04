<?php

namespace App\Support;

use App\Models\Campaign;

class CampaignTokens
{
    /** Texto por defecto del título de selección (mismo que se usaba fijo). */
    public const SELECCION_DEFAULT = 'Hola, [COLABORADOR] seleccione su hijo(a)';

    /** Marcadores que se pueden usar en el título de la vista de selección. */
    public const SELECCION_TOKENS = ['[COLABORADOR]', '[EMPRESA]', '[NOMBRE CAMPAÑA]', '[FECHAFIN]'];

    /** Texto por defecto de la vista "Tu selección ha finalizado". */
    public const CONFIRMACION_DEFAULT = 'Hemos registrado los juguetes que has elegido. En breve recibirás un correo electrónico con la confirmación de las referencias seleccionadas. Si no lo recibes, por favor permanece atento a las notificaciones del área de Gestión Humana de tu empresa, para coordinar la entrega de tus juguetes.';

    /** Marcadores disponibles en el texto de confirmación (no dependen del colaborador, que ya cerró sesión). */
    public const CONFIRMACION_TOKENS = ['[EMPRESA]', '[NOMBRE CAMPAÑA]', '[FECHAFIN]'];

    /**
     * Reemplazo tolerante de marcadores [TOKEN]: ignora espacios, NBSP,
     * mayúsculas y caracteres invisibles. Los marcadores sin valor se dejan tal cual.
     *
     * @param array<string, string> $map valores ya listos para HTML, indexados por TOKEN en mayúsculas
     */
    public static function replace(string $html, array $map): string
    {
        // quita caracteres invisibles que mete algún editor
        $html = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $html);

        return preg_replace_callback('~\[\s*([^\]]+?)\s*\]~u', function ($m) use ($map) {
            $key = strtr($m[1], ["\xC2\xA0" => ' ']); // NBSP → espacio
            $key = preg_replace('/\s+/u', ' ', trim($key));
            $key = mb_strtoupper($key, 'UTF-8');

            // alias seguros (por si alguien escribe NOMBRE_CAMPAÑA)
            $aliases = [
                'NOMBRE_CAMPAÑA' => 'NOMBRE CAMPAÑA',
                'NOMBRE CAMPANA' => 'NOMBRE CAMPAÑA', // sin tilde
            ];
            if (isset($aliases[$key])) $key = $aliases[$key];

            return $map[$key] ?? $m[0];
        }, $html);
    }

    /**
     * Texto de la vista "Tu selección ha finalizado", en HTML seguro.
     * Los saltos de línea se conservan y los marcadores se reemplazan
     * con datos de la campaña y de la empresa.
     */
    public static function textoConfirmacion(?Campaign $campaign): string
    {
        $plantilla = trim((string) $campaign?->mensaje_confirmacion) !== ''
            ? (string) $campaign->mensaje_confirmacion
            : self::CONFIRMACION_DEFAULT;

        $empresa = (string) ($campaign?->empresa?->nombre ?? '');
        $fin = $campaign?->fechafinLocal()?->format('d/m/Y') ?? '';

        return nl2br(self::replace(e($plantilla), [
            'EMPRESA'        => e($empresa),
            'NOMBRE CAMPAÑA' => e((string) ($campaign?->nombre ?? '')),
            'FECHAFIN'       => e($fin),
        ]), false);
    }

    /**
     * Título de la vista de selección de hijos, en HTML seguro.
     * El texto de la campaña se escapa antes de reemplazar los marcadores,
     * así que solo el nombre del colaborador se muestra en cursiva.
     */
    public static function tituloSeleccion(Campaign $campaign, string $colaborador, string $empresa = ''): string
    {
        $plantilla = trim((string) $campaign->mensaje_seleccion) !== ''
            ? (string) $campaign->mensaje_seleccion
            : self::SELECCION_DEFAULT;

        $fin = $campaign->fechafinLocal()?->format('d/m/Y') ?? '';

        return self::replace(e($plantilla), [
            'COLABORADOR'    => '<i>' . e($colaborador) . '</i>',
            'EMPRESA'        => e($empresa),
            'NOMBRE CAMPAÑA' => e((string) $campaign->nombre),
            'FECHAFIN'       => e($fin),
        ]);
    }
}
