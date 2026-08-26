<?php

namespace App\Messaging\SMS;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class ZenviaDriver implements MessagingDriver
{
    public function __construct(
        private string $apiKey,
        private string $from,
    ) {}

    public function send(string $phone, string $message): array
    {
        $phone = preg_replace('/\D/', '', $phone);

        $response = Http::withHeaders(['X-API-TOKEN' => $this->apiKey])
            ->post('https://api.zenvia.com/v2/channels/sms/messages', [
                'from'     => $this->from,
                'to'       => $phone,
                'contents' => [['type' => 'text', 'text' => $message]],
            ]);

        if ($response->successful()) return ['success' => true, 'error' => null];
        return ['success' => false, 'error' => $response->json('message') ?? $response->body()];
    }

    public function label(): string { return 'Zenvia SMS'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'zenvia_api_key', 'label' => 'API Token', 'type' => 'password'],
            ['key' => 'zenvia_from',    'label' => 'Remetente (from)', 'type' => 'text'],
        ];
    }
}
