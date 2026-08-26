<?php

namespace App\Messaging\SMS;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class InfobipDriver implements MessagingDriver
{
    public function __construct(
        private string $apiKey,
        private string $baseUrl,
        private string $from,
    ) {}

    public function send(string $phone, string $message): array
    {
        $phone = preg_replace('/\D/', '', $phone);
        $base = rtrim($this->baseUrl, '/');

        $response = Http::withHeaders(['Authorization' => 'App ' . $this->apiKey])
            ->post("{$base}/sms/2/text/advanced", [
                'messages' => [[
                    'from' => $this->from,
                    'destinations' => [['to' => $phone]],
                    'text' => $message,
                ]],
            ]);

        if ($response->successful()) return ['success' => true, 'error' => null];
        return ['success' => false, 'error' => $response->json('requestError.serviceException.text') ?? $response->body()];
    }

    public function label(): string { return 'Infobip SMS'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'infobip_api_key',  'label' => 'API Key',                              'type' => 'password'],
            ['key' => 'infobip_base_url', 'label' => 'Base URL (ex: https://xxxx.api.infobip.com)', 'type' => 'text'],
            ['key' => 'infobip_from',     'label' => 'Remetente (From)',                      'type' => 'text'],
        ];
    }
}
