<?php

namespace App\Jobs;

use App\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class SendSelectionCompletedMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Correo de destino */
    public string $to;

    /** Nombre del usuario para el saludo */
    public string $userName;

    /**
     * Items seleccionados por el colaborador.
     * @var array<int, array{
     *     referencia:string,
     *     toy_nombre?:string,
     *     imagenppal?:string,
     *     idcampaing?:int
     * }>
     */
    public array $items;

    /** Retries/backoff opcional */
    public int $tries = 5;
    public int $timeout = 120;
    public array $backoff = [5, 15, 30, 60];

    /**
     * @param array<int, array<string, mixed>> $items
     */
    public function __construct(string $to, string $userName, array $items)
    {
        $this->to       = $to;
        $this->userName = $userName;
        $this->items    = $items;
    }

    public function handle(): void
    {
        $userEmail = $this->to;
        $userName  = $this->userName;
        $items     = $this->items;

        // Tomar campaña desde los items (primera encontrada)
        $campaignId = collect($items)->pluck('idcampaing')->filter()->first();
        $campaign   = $campaignId ? Campaign::find((int)$campaignId) : null;

        // Resolver banner absoluto (si existe y no es 'ND')
        $bannerUrl = null;
        if ($campaign && !empty($campaign->banner) && $campaign->banner !== 'ND') {
            $bannerUrl = url(Storage::disk('public')->url($campaign->banner));
        }

        try {
            Mail::to($userEmail)->send(
                new \App\Mail\SelectionCompleted(
                    userName: $userName,
                    bannerUrl: $bannerUrl, // puede ser null; el mailable ya maneja placeholder
                    items: $items
                )
            );
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar correo de selección completada', [
                'to'        => $userEmail,
                'campaign'  => $campaignId,
                'error'     => $e->getMessage(),
            ]);
            throw $e; // re-lanzar para que la cola reintente
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Fallo definitivo en SendSelectionCompletedMail', [
            'to'    => $this->to,
            'error' => $e->getMessage(),
        ]);
    }
}
