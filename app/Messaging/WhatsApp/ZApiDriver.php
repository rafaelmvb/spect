<?php

namespace App\Messaging\WhatsApp;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class ZApiDriver implements MessagingDriver
{
    public function __construct(
        private string $instanceId,
        private string $token,
        private string $clientToken = '',
    ) {}

    public function send(string $phone, string $message): array
    {
        $phone = $this->normalizePhone($phone);
        $url = "https://api.z-api.io/instances/{$this->instanceId}/token/{$this->token}/send-text";

        $response = Http::withHeaders(array_filter([
            'Client-Token' => $this->clientToken ?: null,
        ]))->post($url, ['phone' => $phone, 'message' => $message]);

        if ($response->successful()) {
            return ['success' => true, 'error' => null];
        }
        return ['success' => false, 'error' => $response->body()];
    }

    public function label(): string { return 'Z-API'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'zapi_instance_id', 'label' => 'Instance ID', 'type' => 'text'],
            ['key' => 'zapi_token',       'label' => 'Token',        'type' => 'password'],
            ['key' => 'zapi_client_token','label' => 'Client Token (opcional)', 'type' => 'password'],
        ];
    }

    private function normalizePhone(string $phone): string
    {
        return preg_replace('/\D/', '', $phone);
    }
}
