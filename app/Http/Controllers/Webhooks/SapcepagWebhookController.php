<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SapcepagWebhookController extends Controller
{
    use VerificaAssinaturaWebhook;

    public function handle(Request $request): JsonResponse
    {
        $event = $request->input('event');
        $transactionId = $request->input('transaction_id');
        $status = $request->input('status');

        if (empty($transactionId) || ! is_string($transactionId)) {
            return response()->json(['message' => 'transaction_id required'], 400);
        }

        $order = Order::where('gateway', 'sapcepag')->where('gateway_id', $transactionId)->first();
        if (! $order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if (! $this->verifyWebhookSignature('sapcepag', $order->tenant_id, $request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        ProcessPaymentWebhook::dispatchSync('sapcepag', $transactionId, (string) $event, (string) $status, $request->all());

        return response()->json(['received' => true]);
    }

}
