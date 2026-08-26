<?php

namespace App\Messaging\WhatsApp;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class WPPConnectDriver implements MessagingDriver
{
    public function __construct(
        private string $baseUrl,
        private string $secretKey,
        private string $session,
    ) {}

    public function send(string $phone, string $message): array
    {
        $phone = preg_replace('/\D/', '', $phone) . '@c.us';
        $base = rtrim($this->baseUrl, '/');

        // Gera token de sessão
        $tokenRes = Http::post("{$base}/api/{$this->session}/{$this->secretKey}/generate-token");
        $token = $tokenRes->json('token') ?? null;
        if (! $token) {
            return ['success' => false, 'error' => 'Falha ao gerar token WPPConnect'];
        }

        $response = Http::withToken($token)
            ->post("{$base}/api/{$this->session}/send-message", [
                'phone'   => $phone,
                'message' => $message,
                'isGroup' => false,
            ]);

        if ($response->successful()) {
            return ['success' => true, 'error' => null];
        }
        return ['success' => false, 'error' => $response->body()];
    }

    public function label(): string { return 'WPPConnect'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'wppconnect_base_url',   'label' => 'URL base do servidor', 'type' => 'text'],
            ['key' => 'wppconnect_secret_key',  'label' => 'Secret Key',           'type' => 'password'],
            ['key' => 'wppconnect_session',     'label' => 'Nome da sessão',        'type' => 'text'],
        ];
    }
}
