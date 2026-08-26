<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RunScheduleFallback
{
    private const CACHE_KEY = 'schedule_fallback_last_run';

    private const THROTTLE_SECONDS = 55;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    /**
     * Quando o cron não está rodando, executa o schedule automaticamente após enviar a resposta.
     * Usa throttle para não rodar em toda requisição (máx 1x por minuto).
     */
    public function terminate(Request $request, Response $response): void
    {
        $user = $request->user();
        if (! $user || ! $user->canAccessPanel()) {
            return;
        }

        $defaultQueue = (string) config('queue.default', 'sync');
        $connections = config('queue.connections', []);
        if (! is_array($connections) || ! array_key_exists($defaultQueue, $connections)) {
            return;
        }

        $scheduleHeartbeat = Cache::get('schedule_heartbeat');
        if (self::isHeartbeatRecent($scheduleHeartbeat, 5)) {
            return;
        }

        // Cache::add e atomico: so o primeiro request da janela grava e segue.
        // Com get+put, duas requisicoes simultaneas disparavam schedule:run juntas.
        if (! Cache::add(self::CACHE_KEY, now()->toIso8601String(), self::THROTTLE_SECONDS)) {
            return;
        }

        try {
            Artisan::call('schedule:run');
        } catch (\Throwable $e) {
            // Sem rethrow: isto roda depois da resposta e não pode afetar a
            // requisição já entregue. Mas registra, senão a falha some.
            report($e);
        }
    }

    private static function isHeartbeatRecent(?string $value, int $minutes = 5): bool
    {
        if ($value === null || $value === '') {
            return false;
        }
        try {
            $at = Carbon::parse($value);

            return $at->gte(now()->subMinutes($minutes));
        } catch (\Throwable) {
            return false;
        }
    }
}
