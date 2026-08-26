<?php

namespace App\Messaging\SMS;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class SmSdevDriver implements MessagingDriver
{
    public function __construct(private string $apiKey) {}

    public function send(string $phone, string $message): array
    {
        $phone = preg_replace('/\D/', '', $phone);

        $response = Http::get('https://www.smsdev.com.br/v1/send', [
            'key'    => $this->apiKey,
            'type'   => 9,
            'number' => $phone,
            'msg'    => $message,
        ]);

        $data = $response->json();
        if (($data['situacao'] ?? '') === 'OK') return ['success' => true, 'error' => null];
        return ['success' => false, 'error' => $data['descricao'] ?? $response->body()];
    }

    public function label(): string { return 'SMSdev (BR)'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'smsdev_api_key', 'label' => 'API Key', 'type' => 'password'],
        ];
    }
}
