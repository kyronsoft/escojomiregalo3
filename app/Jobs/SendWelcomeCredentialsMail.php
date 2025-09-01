<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendWelcomeCredentialsMail implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public string $to;
  public string $name;
  public string $rawPassword;
  public string $loginUrl;

  /** ID de la campaña */
  public ?int $campaignId = null;

  public ?string $subjectLine = null;

  /** Imagen de fondo opcional (URL absoluta o relativa) */
  public ?string $backgroundImage = null;

  public int $tries = 5;
  public int $timeout = 120;
  public array $backoff = [5, 15, 30, 60];

  public function __construct(
    string $to,
    string $name,
    string $rawPassword,
    string $loginUrl,
    ?int $campaignId = null,
    ?string $subjectLine = null,
    ?string $backgroundImage = null
  ) {
    $this->to              = $to;
    $this->name            = $name;
    $this->rawPassword     = $rawPassword;
    $this->loginUrl        = $loginUrl;
    $this->campaignId      = $campaignId;
    $this->subjectLine     = $subjectLine;
    $this->backgroundImage = $backgroundImage;
  }

  public function handle(): void
  {
    $absoluteLogin = \Illuminate\Support\Str::startsWith($this->loginUrl, ['http://', 'https://'])
      ? $this->loginUrl
      : url($this->loginUrl);

    $campaign = $this->campaignId
      ? \App\Models\Campaign::with('empresa')->find($this->campaignId)
      : null;

    $empresa = $campaign?->empresa;
    if (!$empresa && $campaign?->nit) {
      $nit = trim((string)$campaign->nit);
      $empresa = \App\Models\Empresa::whereRaw('TRIM(nit) = ?', [$nit])->first();
    }

    $empresaNom     = $empresa?->nombre ?: config('app.name', 'Su empresa');
    $empresaDir     = $empresa?->direccion ?: null;
    $empresaCiudad  = $empresa?->ciudad ?: null;

    // Logo izquierdo (empresa) en URL absoluta
    $logoUrlAbs = $empresa?->logo
      ? url(\Illuminate\Support\Facades\Storage::disk('public')->url($empresa->logo))
      : asset('assets/images/logo/logo.png');

    // Logo derecho fijo (More)
    $rightLogoAbs = url('assets/images/moreproducts/Logo_More.png');

    // Background (opcional) a URL absoluta
    $bgUrlAbs = null;
    if (!empty($this->backgroundImage)) {
      $bgUrlAbs = \Illuminate\Support\Str::startsWith($this->backgroundImage, ['http://', 'https://'])
        ? $this->backgroundImage
        : url($this->backgroundImage);
    }

    // Mailtext con tokens
    $mailtextRaw = (string)($campaign->mailtext ?? '');
    $fechafinStr = '';
    if ($campaign && $campaign->fechafin) {
      $fechafinStr = $campaign->fechafin instanceof \Carbon\Carbon
        ? $campaign->fechafin->format('d-M-Y')
        : (new \Carbon\Carbon($campaign->fechafin))->format('d-M-Y');
    }
    $esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    $replacements = [
      '[COLABORADOR]'     => $esc($this->name),
      '[EMPRESA]'         => $esc($empresaNom),
      '[NOMBRE CAMPAÑA]'  => $esc($campaign->nombre ?? ''),
      '[LINK]'            => '<a href="' . $absoluteLogin . '" class="btn-primary" style="color:#fff;">aquí</a>',
      '[LINKHTML]'        => '<a href="' . $absoluteLogin . '">' . $esc($absoluteLogin) . '</a>',
      '[FECHAFIN]'        => $esc($fechafinStr),
    ];
    $mailtextHtml = strtr($mailtextRaw, $replacements);

    // Footer
    $footerHtml = $this->buildFooterHtml(
      empresaNombre: $empresaNom,
      empresaDir: $empresaDir,
      empresaCiudad: $empresaCiudad
    );

    // HTML del correo (sin Blade). El “banner” ahora es el logo de More.
    $html = $this->buildEmailHtml(
      leftLogoUrl: $logoUrlAbs,
      rightLogoUrl: $rightLogoAbs,
      empresaNombre: $empresaNom,
      mailtextHtml: $mailtextHtml,
      footerHtml: $footerHtml,
      backgroundUrl: $bgUrlAbs
    );

    \Illuminate\Support\Facades\Mail::to($this->to)
      ->send(new \App\Mail\RawHtmlMail(
        subjectLine: $this->subjectLine,
        htmlBody: $html
      ));
  }

  public function failed(\Throwable $e): void
  {
    Log::error('Fallo envío de credenciales', [
      'to'    => $this->to,
      'error' => $e->getMessage(),
    ]);
  }

  private function buildFooterHtml(string $empresaNombre, ?string $empresaDir, ?string $empresaCiudad): string
  {
    $empresaNombreEsc = htmlspecialchars($empresaNombre, ENT_QUOTES, 'UTF-8');
    $dir = $empresaDir ? htmlspecialchars($empresaDir, ENT_QUOTES, 'UTF-8') : '';
    $ciu = $empresaCiudad ? htmlspecialchars($empresaCiudad, ENT_QUOTES, 'UTF-8') : '';
    $year = date('Y');

    $loc  = trim($dir . ($dir && $ciu ? ' · ' : '') . $ciu);
    $home = htmlspecialchars(url('/'), ENT_QUOTES, 'UTF-8');
    $priv = htmlspecialchars(url('/privacy'), ENT_QUOTES, 'UTF-8');
    $terms = htmlspecialchars(url('/terms'), ENT_QUOTES, 'UTF-8');
    $uns  = htmlspecialchars(url('/unsubscribe'), ENT_QUOTES, 'UTF-8');

    return <<<HTML
<table style="width:650px; margin:30px auto 0 auto;" cellpadding="0" cellspacing="0" role="presentation">
  <tbody>
    <tr>
      <td style="padding:18px 12px; background:#0f1115; border-radius:8px; text-align:center;">
        <p style="color:#fff; font-weight:700; letter-spacing:.3px; margin:0 0 6px 0;">{$empresaNombreEsc}</p>
        <p style="color:#b8bcc6; margin:0 0 8px 0; font-size:12px;">{$loc}</p>
        <p style="margin:10px 0 0 0; font-size:12px;">
          <a href="{$home}"  style="color:#b8bcc6; margin:0 8px;">Inicio</a>
          <a href="{$priv}"  style="color:#b8bcc6; margin:0 8px;">Privacidad</a>
          <a href="{$terms}" style="color:#b8bcc6; margin:0 8px;">Términos</a>
          <a href="{$uns}"   style="color:#b8bcc6; margin:0 8px;">Desuscribirse</a>
        </p>
        <p style="color:#8f95a3; margin-top:10px; font-size:12px;">© {$year} {$empresaNombreEsc}. Todos los derechos reservados.</p>
      </td>
    </tr>
  </tbody>
</table>
HTML;
  }

  /**
   * Plantilla HTML completa del correo (sin Blade), con background opcional.
   * Ahora, el “rightLogoUrl” reemplaza al antiguo banner.
   */
  private function buildEmailHtml(
    string $leftLogoUrl,
    string $rightLogoUrl,
    string $empresaNombre,
    string $mailtextHtml,
    string $footerHtml,
    ?string $backgroundUrl = null
  ): string {
    $empresaNombreEsc = htmlspecialchars($empresaNombre, ENT_QUOTES, 'UTF-8');
    $leftLogo  = htmlspecialchars($leftLogoUrl, ENT_QUOTES, 'UTF-8');
    $rightLogo = htmlspecialchars($rightLogoUrl, ENT_QUOTES, 'UTF-8');
    $bgEsc     = $backgroundUrl ? htmlspecialchars($backgroundUrl, ENT_QUOTES, 'UTF-8') : null;

    // Estilos base + background
    $bodyBase = "margin:0; padding:30px 0; background-color:#f6f7fb; -webkit-text-size-adjust:100%; -ms-text-size-adjust:100%;";
    $bodyBg   = $bgEsc ? " background-image:url('{$bgEsc}'); background-size:cover; background-position:center top; background-repeat:no-repeat;" : "";
    $tableBgAttr = $bgEsc ? ' background="' . $bgEsc . '"' : '';

    // Fallback Outlook VML para fondo
    $vml = '';
    if ($bgEsc) {
      $vml = <<<VML
<!--[if gte mso 9]>
<v:background xmlns:v="urn:schemas-microsoft-com:vml" fill="t">
  <v:fill type="tile" src="{$bgEsc}" color="#f6f7fb"/>
</v:background>
<![endif]-->
VML;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta http-equiv="X-UA-Compatible" content="IE=edge" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>{$empresaNombreEsc} – Notificación</title>
<link href="https://fonts.googleapis.com/css?family=Work+Sans:300,400,600,700|Nunito:300,400,600,700|Poppins:300,400,600,700" rel="stylesheet" />
<style>
  a { text-decoration:none; }
  p { font-size:13px; line-height:1.7; letter-spacing:0.3px; margin:0 0 10px; color:#333; }
  h6 { font-size:16px; margin:0 0 18px 0; color:#111; }
  .btn-primary { display:inline-block; padding:10px 16px; background:#111; color:#fff !important; border-radius:4px; font-weight:600; }
  .text-center { text-align:center; }
</style>
</head>
<body style="{$bodyBase}{$bodyBg}">
{$vml}
<table{$tableBgAttr} style="width:100%;" cellpadding="0" cellspacing="0" role="presentation">
  <tbody>
    <tr>
      <td>

        <!-- Header -->
        <table style="background-color:transparent; width:100%;" cellpadding="0" cellspacing="0" role="presentation">
          <tbody><tr><td>
            <table style="width:650px; margin:0 auto 30px auto; background:rgba(255,255,255,0.0);" cellpadding="0" cellspacing="0" role="presentation">
              <tbody>
                <tr>
                  <td style="vertical-align:middle;">
                    <!-- Logo empresa (100px alto) -->
                    <img src="{$leftLogo}" alt="Logo" style="height:100px; width:auto; display:block;">
                  </td>
                  <td style="text-align:right; vertical-align:middle;">
                    <!-- Logo More (100px alto) -->
                    <img src="{$rightLogo}" alt="MoreProducts Logo" style="height:100px; width:auto; display:inline-block;">
                  </td>
                </tr>
              </tbody>
            </table>
          </td></tr></tbody>
        </table>

        <!-- Contenido principal -->
        <table style="width:650px; margin:0 auto; background-color:#fff; border-radius:8px;" cellpadding="0" cellspacing="0" role="presentation">
          <tbody>
            <tr>
              <td style="padding:30px;">
                {$mailtextHtml}
              </td>
            </tr>
          </tbody>
        </table>

        <!-- Footer -->
        {$footerHtml}

      </td>
    </tr>
  </tbody>
</table>
</body>
</html>
HTML;
  }
}
