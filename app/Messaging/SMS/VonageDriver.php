<?php

namespace App\Messaging\SMS;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class VonageDriver implements MessagingDriver
{
    public function __construct(
        private string $apiKey,
        private string $apiSecret,
        private string $from,
    ) {}

    public function send(string $phone, string $message): array
    {
        $phone = preg_replace('/\D/', '', $phone);

        $response = Http::post('https://rest.nexmo.com/sms/json', [
            'api_key'    => $this->apiKey,
            'api_secret' => $this->apiSecret,
            'to'         => $phone,
            'from'       => $this->from,
            'text'       => $message,
        ]);

        $status = $response->json('messages.0.status') ?? '1';
        if ($status === '0') return ['success' => true, 'error' => null];
        return ['success' => false, 'error' => $response->json('messages.0.error-text') ?? $response->body()];
    }

    public function label(): string { return 'Vonage (Nexmo) SMS'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'vonage_api_key',    'label' => 'API Key',    'type' => 'text'],
            ['key' => 'vonage_api_secret', 'label' => 'API Secret', 'type' => 'password'],
            ['key' => 'vonage_from',       'label' => 'Remetente',  'type' => 'text'],
        ];
    }
}
