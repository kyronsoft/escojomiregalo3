<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class SelectionCompleted extends Mailable
{
    use Queueable, SerializesModels;

    private string $userName;
    private ?string $bannerUrl;
    /** @var array<int, array{referencia:string, toy_nombre?:string, imagenppal?:string, idcampaing?:int}> */
    private array $items;

    public function __construct(string $userName, ?string $bannerUrl, array $items)
    {
        $this->userName  = $userName;
        $this->bannerUrl = $bannerUrl;
        $this->items     = $items;
    }

    public function build()
    {
        $html = $this->renderHtml(
            userName: $this->userName,
            bannerUrl: $this->bannerUrl,
            items: $this->items,
            currentYear: (int)date('Y')
        );

        return $this->subject('Tu selección ha sido registrada')->html($html);
    }

    private function toAbsolute(?string $url): ?string
    {
        if (!$url) return null;
        if (Str::startsWith($url, ['http://', 'https://'])) return $url;
        return url($url);
    }

    private function itemImageUrl(array $it): string
    {
        $imgRel = trim((string)($it['imagenppal'] ?? ''));
        if ($imgRel === '') {
            return asset('assets/images/email-template/placeholder.png');
        }
        if (Str::startsWith($imgRel, ['http://', 'https://'])) {
            return $imgRel;
        }
        $imgRel = ltrim($imgRel, '/');
        $idc = (int)($it['idcampaing'] ?? 0);
        $path = Str::startsWith($imgRel, 'campaign_toys/')
            ? $imgRel
            : "campaign_toys/{$idc}/{$imgRel}";
        return url(Storage::url($path));
    }

    private function esc(string $v): string
    {
        return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
    }

    private function renderHtml(string $userName, ?string $bannerUrl, array $items, int $currentYear): string
    {
        $banner = $this->toAbsolute($bannerUrl) ?: asset('assets/images/email-template/banner-default.jpg');
        $logoMore = $this->toAbsolute(asset('assets/images/moreproducts/Logo_More.png'));

        $rows = '';
        foreach ($items as $it) {
            $img = $this->itemImageUrl($it);
            $toy = $this->esc((string)($it['toy_nombre'] ?? 'Juguete'));
            $ref = $this->esc((string)($it['referencia']   ?? ''));
            $rows .= <<<HTML
<tr>
  <td class="text-center" style="vertical-align:middle; background:#ffffff;">
    <img src="{$img}" alt="{$toy}" width="80" style="border-radius:6px; display:inline-block;">
  </td>
  <td style="background:#ffffff;">
    <h5 style="margin:0 0 6px; color:#222; font-weight:600;">{$toy}</h5>
    <p class="muted" style="margin:0;">Ref: <strong>{$ref}</strong></p>
  </td>
  <td class="text-center" style="vertical-align:middle; background:#ffffff;">
    <span style="display:inline-block; background:#e9ecef; color:#333; border-radius:12px; padding:4px 10px; font-weight:600;">1</span>
  </td>
</tr>
HTML;
        }

        $userNameEsc = $this->esc($userName);

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>Resumen de selección</title>
<link rel="icon" href="{$this->toAbsolute(asset('assets/images/favicon.png'))}" type="image/x-icon" />
<style type="text/css">
  body{margin:0 auto; width:650px; font-family:Work Sans,Arial,sans-serif; background-color:#f6f7fb; display:block;}
  table{border-collapse:collapse; width:100%;}
  .wrap{padding:30px; background:#ffffff; box-shadow:0 0 14px -4px rgba(0,0,0,.27);}
  /* ⬇️ Tabla de juguetes: blanco */
  .order-detail{border:1px solid #ddd; margin-top:20px; background:#ffffff;}
  .order-detail th{font-size:14px; padding:12px; background:#fafafa; text-transform:uppercase; letter-spacing:.3px;}
  .order-detail td{padding:12px; vertical-align:top; border-top:1px solid #eee; background:#ffffff;}
  .text-center{text-align:center;}
  .muted{color:#777; font-size:14px;}
  .banner{max-width:100%; height:auto; border-radius:6px;}
  .footer{margin-top:30px;}
    .footer-inner{
    background:#ffffff;            /* fondo claro para contrastar con el logo */
    color:#1f2937;                 /* texto principal oscuro */
    border:1px solid #e5e7eb;      /* sutíl borde para separar del contenido */
    border-radius:8px;
    padding:22px 16px;
    text-align:center;
    }
    .footer a{
    color:#0d6efd;                 /* link azul legible sobre fondo blanco */
    text-decoration:none;
    margin:0 8px;
    font-size:12px;
    }
    .footer a:hover{ text-decoration:underline; }
    .footer .brand-img{
    max-height:80px;
    width:auto;
    display:inline-block;
    margin-bottom:8px;
    }
    .footer .tag{
    color:#374151;                 /* gris oscuro, buen contraste */
    font-size:12px;
    margin:6px 0 12px;
    font-weight:600;
    }
    .footer .copy{
    color:#4b5563;                 /* gris medio, legible */
    font-size:12px;
    margin-top:10px;
    }

    /* Opcional: franja con colores de marca en la parte superior del footer */
    .footer .accent{
    height:4px;
    margin:0 0 12px 0;
    background:linear-gradient(90deg,#FFCE00 0%,#FFCE00 50%,#9B999F 50%,#9B999F 100%);
    }
</style>
</head>
<body style="margin:20px auto;">
  <table align="center" border="0" cellpadding="0" cellspacing="0" class="wrap">
    <tbody>
      <tr>
        <td>
          <table role="presentation">
            <tbody>
              <tr>
                <td class="text-center" style="padding:0;">
                <div style="width:100%; max-height:50vh; overflow:hidden;">
                    <img src="{$banner}" alt="Banner campaña"
                        style="display:block; width:100%; height:auto;">
                </div>
                </td>
              </tr>
              <tr>
                <td style="padding-top:10px;">
                  <p style="font-size:18px; margin:8px 0;"><b>Hola, {$userNameEsc}</b></p>
                  <p class="muted" style="margin:6px 0 0;">La selección de juguetes que realizaste es la siguiente.</p>
                </td>
              </tr>
            </tbody>
          </table>

          <table class="order-detail" border="0" cellpadding="0" cellspacing="0" align="left" style="background:#ffffff;">
            <thead>
              <tr>
                <th style="width:110px;">Imagen</th>
                <th>Juguete</th>
                <th style="width:110px;" class="text-center">Cantidad</th>
              </tr>
            </thead>
            <tbody>
              {$rows}
            </tbody>
          </table>

          <!-- Footer negro -->
          <div class="footer">
            <div class="footer-inner">
              <img src="{$logoMore}" alt="More Products" class="brand-img">
              <div class="tag">Impulsando la grandeza de nuestro país</div>

              <div style="margin:10px 0 2px;">
                <a href="https://moreproducts.com/" target="_blank" rel="noopener">Inicio</a>
                <a href="https://moreproducts.com/nosotros/" target="_blank" rel="noopener">Nosotros</a>
                <a href="https://moreproducts.com/negocios/" target="_blank" rel="noopener">Negocios</a>
                <a href="https://moreproducts.com/contacto/" target="_blank" rel="noopener">Contáctenos</a>
              </div>
              <div style="margin:6px 0 10px;">
                <a href="https://moreproducts.com/legales/" target="_blank" rel="noopener">Legales</a>
                <a href="https://moreproducts.dataprotected.co/" target="_blank" rel="noopener">Tratamiento de datos</a>
                <a href="https://moreproducts.com/aviso-de-privacidad/" target="_blank" rel="noopener">Aviso de privacidad</a>
                <a href="https://www.linkedin.com/company/moreproducts" target="_blank" rel="noopener">LinkedIn</a>
              </div>

              <div class="copy">© {$currentYear} MORE PRODUCTS. Todos los derechos reservados | Bogotá – Colombia</div>
            </div>
          </div>

        </td>
      </tr>
    </tbody>
  </table>
</body>
</html>
HTML;
    }
}
