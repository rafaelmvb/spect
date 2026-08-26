<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Jobs\SendAccessEmailJob;
use App\Support\QueueHealth;
use Illuminate\Support\Facades\Log;

class SendAccessEmailOnOrderCompleted
{
    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;

        // Sem worker ativo, enfileirar é o mesmo que não enviar: cai para
        // síncrono. Com worker, o SMTP sai da requisição do webhook do gateway.
        if (QueueHealth::precisaRodarSincrono()) {
            Log::info('SendAccessEmailOnOrderCompleted: sem worker, enviando de forma síncrona.', [
                'order_id' => $order->id,
            ]);

            try {
                SendAccessEmailJob::dispatchSync($order->id);
            } catch (\Throwable $e) {
                // Sem rethrow: o pagamento já foi confirmado e propagar aqui
                // devolveria erro ao gateway, provocando retentativa do webhook.
                Log::error('SendAccessEmailOnOrderCompleted: falha no envio síncrono.', [
                    'order_id' => $order->id,
                    'message' => $e->getMessage(),
                ]);
            }

            return;
        }

        SendAccessEmailJob::dispatch($order->id);
    }
}
