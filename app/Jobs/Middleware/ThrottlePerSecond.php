<?php

namespace App\Jobs\Middleware;

use Illuminate\Support\Facades\RateLimiter;

/**
 * Limita jobs por SEGUNDO (Laravel 10 no tiene Limit::perSecond()).
 * Usa una ventana de 1 s compartida entre todos los workers vía cache;
 * si se supera el cupo, el job se reprograma sin consumir intentos reales
 * (requiere retryUntil() en el job en lugar de $tries).
 */
class ThrottlePerSecond
{
    public function __construct(
        private string $key,
        private int $maxPerSecond = 8
    ) {}

    public function handle($job, $next)
    {
        $bucket = 'throttle:' . $this->key . ':' . time();

        if (RateLimiter::tooManyAttempts($bucket, $this->maxPerSecond)) {
            $job->release(random_int(1, 3));
            return;
        }

        RateLimiter::hit($bucket, 2);

        return $next($job);
    }
}
