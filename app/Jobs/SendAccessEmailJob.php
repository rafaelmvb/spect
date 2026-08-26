<?php

namespace App\Jobs;

use App\Events\AccessDeliveryReady;
use App\Models\Order;
use App\Services\AccessEmailService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Envia o e-mail de acesso da compra.
 *
 * Estava inline no listener de OrderCompleted, ou seja, dentro da requisição do
 * webhook do gateway: um SMTP lento estourava o timeout do gateway e provocava
 * retentativa. Aqui sai do caminho crítico quando há worker.
 */
class SendAccessEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120];

    public function __construct(public int $orderId) {}

    public function handle(AccessEmailService $accessEmailService): void
    {
        $order = Order::find($this->orderId);
        if (! $order) {
            Log::warning('SendAccessEmailJob: pedido não encontrado.', ['order_id' => $this->orderId]);

            return;
        }

        // WhatsApp (AutoZap) recebe os dados de acesso prontos — best-effort.
        $access = $accessEmailService->getAccessDataForOrder($order);
        if (is_array($access)) {
            AccessDeliveryReady::dispatch($order, $access);
        }

        if (! $accessEmailService->sendForOrder($order)) {
            Log::warning('SendAccessEmailJob: sendForOrder retornou false.', ['order_id' => $order->id]);
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SendAccessEmailJob: falhou após as retentativas.', [
            'order_id' => $this->orderId,
            'message' => $e->getMessage(),
        ]);
    }
}
