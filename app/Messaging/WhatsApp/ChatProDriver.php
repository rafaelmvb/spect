<?php

namespace App\Messaging\WhatsApp;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class ChatProDriver implements MessagingDriver
{
    public function __construct(
        private string $sessionKey,
    ) {}

    public function send(string $phone, string $message): array
    {
        $phone = preg_replace('/\D/', '', $phone) . '@c.us';
        $response = Http::withHeaders(['session-key' => $this->sessionKey])
            ->post('https://api.chatpro.com.br/api/v1/send_message', [
                'number'  => $phone,
                'message' => $message,
            ]);

        if ($response->successful() && ($response->json('status') ?? '') !== 'error') {
            return ['success' => true, 'error' => null];
        }
        return ['success' => false, 'error' => $response->json('message') ?? $response->body()];
    }

    public function label(): string { return 'ChatPro'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'chatpro_session_key', 'label' => 'Session Key', 'type' => 'password'],
        ];
    }
}
