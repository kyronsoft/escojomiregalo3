<?php

namespace App\Mail;

use App\Models\Campaign;
use Illuminate\Support\Str;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class UserWelcomeCredentialsGmail extends Mailable
{
    use Queueable, SerializesModels;

    /** Datos base */
    public string $name;
    public string $emailUser;
    public string $rawPassword;
    public string $loginUrl;

    /** Metadatos opcionales para metacampos del Blade */
    public ?Campaign $campaign;
    public ?string $empresa;
    public ?string $logoUrl;

    /** Subject opcional (tiene prioridad sobre el de la campaña) */
    public ?string $subjectLine;

    /**
     * @param string        $name
     * @param string        $emailUser
     * @param string        $rawPassword
     * @param string        $loginUrl          URL absoluta o relativa (tu Job ya la vuelve absoluta)
     * @param Campaign|null $campaign          (Opcional) Para metacampos en el Blade
     * @param string|null   $empresa           (Opcional) Nombre de la empresa (fallback si la campaña no lo trae)
     * @param string|null   $logoUrl           (Opcional) URL absoluta del logo a mostrar
     * @param string|null   $subjectLine       (Opcional) Subject explícito; si no, usa $campaign->subject o uno por defecto
     */
    public function __construct(
        string $name,
        string $emailUser,
        string $rawPassword,
        string $loginUrl,
        ?Campaign $campaign = null,
        ?string $empresa = null,
        ?string $logoUrl = null,
        ?string $subjectLine = null,
    ) {
        $this->name        = $name;
        $this->emailUser   = $emailUser;
        $this->rawPassword = $rawPassword;
        $this->loginUrl    = $loginUrl;

        $this->campaign    = $campaign;
        $this->empresa     = $empresa;
        $this->logoUrl     = $logoUrl;
        $this->subjectLine = $subjectLine;
    }

    public function build()
    {
        $absoluteLogin = Str::startsWith($this->loginUrl, ['http://', 'https://'])
            ? $this->loginUrl
            : url($this->loginUrl);

        // PRIORIDAD: lo que vino del Job (ya resuelto) > relación > app.name
        $empresaNom = $this->empresa
            ?? optional($this->campaign?->empresa)->nombre
            ?? config('app.name', 'Su empresa');

        $logoUrlAbs = $this->logoUrl
            ?? (optional($this->campaign?->empresa)->logo
                ? url(Storage::disk('public')->url($this->campaign->empresa->logo))
                : asset('assets/images/logo/logo.png'));

        $campName = $this->campaign->nombre ?? 'Campaña';
        $campEnd  = optional($this->campaign->fechafin)->format('Y-m-d') ?? '';

        $subject = $this->subjectLine
            ?? ($this->campaign->subject ?? 'Tus credenciales de acceso');

        $introHtml = null;
        if (!empty($this->campaign?->mailtext)) {
            $introHtml = $this->renderTokens($this->campaign->mailtext, [
                'COLABORADOR'    => e($this->name),
                'EMPRESA'        => e($empresaNom),
                'NOMBRE CAMPAÑA' => e($campName),
                'FECHAFIN'       => e($campEnd),
                'LINK'           => '<a href="' . e($absoluteLogin) . '" class="btn" target="_blank" rel="noopener">Ingresar</a>',
                'LINKHTML'       => '<a href="' . e($absoluteLogin) . '" target="_blank" rel="noopener">' . e($absoluteLogin) . '</a>',
            ]);
        }

        return $this->subject($subject)
            ->view('emails.welcome-credentials')
            ->with([
                'name'        => $this->name,
                'emailUser'   => $this->emailUser,
                'rawPassword' => $this->rawPassword,
                'loginUrl'    => $absoluteLogin,
                'logoUrl'     => $logoUrlAbs,   // listo para <img>
                'empresaNom'  => $empresaNom,   // por si no hay mailtext
                'introHtml'   => $introHtml,    // HTML con tokens ya reemplazados
            ]);
    }


    /** Reemplazo tolerante: ignora espacios/case. */
    private function renderTokens(string $html, array $map): string
    {
        // quita caracteres invisibles que mete algún editor
        $html = preg_replace('/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $html);

        // reemplazo genérico: captura [ ... ] y normaliza la clave
        return preg_replace_callback('~\[\s*([^\]]+?)\s*\]~u', function ($m) use ($map) {
            $key = $m[1];

            // normaliza: mayúsculas, convierte NBSP a espacio y colapsa múltiple
            $key = strtr($key, ["\xC2\xA0" => ' ']); // NBSP → espacio
            $key = preg_replace('/\s+/u', ' ', trim($key));
            $key = mb_strtoupper($key, 'UTF-8');

            // alias seguros (por si alguien escribe NOMBRE_CAMPAÑA)
            $aliases = [
                'NOMBRE_CAMPAÑA' => 'NOMBRE CAMPAÑA',
                'NOMBRE CAMPANA' => 'NOMBRE CAMPAÑA', // sin tilde
            ];
            if (isset($aliases[$key])) $key = $aliases[$key];

            return $map[$key] ?? $m[0]; // si no existe, deja el token tal cual
        }, $html);
    }
}
