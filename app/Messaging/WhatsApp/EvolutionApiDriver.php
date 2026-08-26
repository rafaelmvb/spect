<?php

namespace App\Messaging\WhatsApp;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class EvolutionApiDriver implements MessagingDriver
{
    public function __construct(
        private string $baseUrl,
        private string $apiKey,
        private string $instance,
    ) {}

    public function send(string $phone, string $message): array
    {
        $phone = preg_replace('/\D/', '', $phone);
        $url = rtrim($this->baseUrl, '/') . '/message/sendText/' . $this->instance;

        $response = Http::withHeaders(['apikey' => $this->apiKey])
            ->post($url, ['number' => $phone, 'text' => $message]);

        if ($response->successful()) {
            return ['success' => true, 'error' => null];
        }
        return ['success' => false, 'error' => $response->body()];
    }

    public function label(): string { return 'Evolution API'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'evolution_base_url', 'label' => 'URL base (ex: https://evolution.seusite.com)', 'type' => 'text'],
            ['key' => 'evolution_api_key',  'label' => 'API Key', 'type' => 'password'],
            ['key' => 'evolution_instance', 'label' => 'Nome da instância', 'type' => 'text'],
        ];
    }
}
