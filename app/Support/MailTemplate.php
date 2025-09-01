<?php

namespace App\Support;

use App\Models\Campaign;
use App\Models\Colaborador;

class MailTemplate
{
    /**
     * Renderiza el HTML final para el correo de invitación de una campaña.
     * Reemplaza tokens por valores reales y genera el botón/URL de login.
     */
    public static function renderCampaignInvitation(Campaign $campaign, Colaborador $colaborador): string
    {
        // URL absoluta al login (requiere APP_URL configurado)
        $loginUrl = route('login');

        // Botón para email (estilos inline, seguros para clientes)
        $buttonHtml = '<a href="' . e($loginUrl) . '" ' .
            'style="display:inline-block;background:#0d6efd;color:#fff;text-decoration:none;' .
            'padding:10px 16px;border-radius:4px;font-family:Arial,Helvetica,sans-serif" ' .
            'target="_blank" rel="noopener">Ingresar y escoger</a>';

        // Valores “seguros” para inyectar en HTML
        $empresa   = e((string)($campaign->empresa ?? ''));         // ajusta según tu esquema
        $nomCamp   = e((string)($campaign->nombre  ?? ''));
        $colabNom  = e((string)($colaborador->nombre ?? ''));
        $fechaFin  = $campaign->fechafin ? $campaign->fechafin->format('d/m/Y') : '';

        // Reemplazos
        $map = [
            '[COLABORADOR]'   => $colabNom,
            '[EMPRESA]'       => $empresa,
            '[NOMBRE CAMPAÑA]' => $nomCamp,
            '[LINK]'          => $buttonHtml,      // HTML (no escapado)
            '[LINKHTML]'      => e($loginUrl),     // solo URL
            '[FECHAFIN]'      => e($fechaFin),
        ];

        // Reemplazo directo; preserva el resto del HTML del editor
        $html = strtr((string)$campaign->mailtext, $map);

        return $html;
    }
}
