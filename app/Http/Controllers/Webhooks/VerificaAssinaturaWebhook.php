<?php

namespace App\Http\Controllers\Webhooks;

use App\Models\GatewayCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Verificação HMAC do corpo do webhook (header X-Webhook-Signature: sha256=hex).
 *
 * Estava duplicada em Asaas, PushinPay, Sapcepag e Spacepag, com a mesma falha:
 * sem webhook_secret configurado, qualquer POST era aceito. A proteção efetiva
 * hoje é ProcessPaymentWebhook reconsultar o status na API do gateway antes de
 * marcar pago — o que este trait acrescenta é visibilidade (log de quem está
 * sem secret) e a opção de exigir assinatura via
 * SPECTRA_WEBHOOKS_REQUIRE_SIGNATURE=true, depois que os secrets estiverem todos
 * configurados.
 */
trait VerificaAssinaturaWebhook
{
    protected function verifyWebhookSignature(string $gatewaySlug, ?int $tenantId, Request $request): bool
    {
        $credential = GatewayCredential::forTenant($tenantId)
            ->where('gateway_slug', $gatewaySlug)
            ->where('is_connected', true)
            ->first();

        $secret = null;
        if ($credential) {
            $credentials = $credential->getDecryptedCredentials();
            $secret = $credentials['webhook_secret'] ?? null;
        }

        if ($secret === null || $secret === '') {
            $exigeAssinatura = (bool) config('spectra.webhooks.require_signature', false);

            Log::warning('Webhook aceito sem verificação de assinatura: webhook_secret não configurado.', [
                'gateway' => $gatewaySlug,
                'tenant_id' => $tenantId,
                'rejeitado' => $exigeAssinatura,
            ]);

            return ! $exigeAssinatura;
        }

        $signature = $request->header('X-Webhook-Signature') ?? $request->header('X-Signature');
        if (! is_string($signature) || $signature === '') {
            Log::warning('Webhook rejeitado: assinatura ausente.', [
                'gateway' => $gatewaySlug,
                'tenant_id' => $tenantId,
            ]);

            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $secret);
        $confere = hash_equals($expected, $signature);

        if (! $confere) {
            Log::warning('Webhook rejeitado: assinatura não confere.', [
                'gateway' => $gatewaySlug,
                'tenant_id' => $tenantId,
            ]);
        }

        return $confere;
    }
}
