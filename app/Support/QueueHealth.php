<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Diz se dá para confiar na fila neste momento.
 *
 * Boa parte das instalações roda em hospedagem compartilhada sem worker: nesses
 * casos enfileirar é o mesmo que jogar fora. QueueHeartbeatJob marca o cache a
 * cada minuto quando existe worker; sem batida recente, o chamador envia
 * síncrono em vez de enfileirar.
 */
class QueueHealth
{
    private const CACHE_KEY = 'queue_heartbeat';

    private const MINUTOS_TOLERANCIA = 3;

    public static function workerAtivo(): bool
    {
        if (config('queue.default') === 'sync') {
            return false;
        }

        $heartbeat = Cache::get(self::CACHE_KEY);
        if (! is_string($heartbeat) || $heartbeat === '') {
            return false;
        }

        try {
            $ultima = Carbon::parse($heartbeat);
        } catch (\Throwable) {
            return false;
        }

        return $ultima->gte(now()->subMinutes(self::MINUTOS_TOLERANCIA));
    }

    /**
     * Inverso de workerAtivo(), com a exceção do ambiente local — onde é comum
     * não haver worker e o silêncio atrapalha mais que a lentidão.
     */
    public static function precisaRodarSincrono(): bool
    {
        if (app()->environment('local')) {
            return true;
        }

        return ! self::workerAtivo();
    }
}
