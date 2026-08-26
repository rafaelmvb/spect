<?php

namespace App\Messaging\WhatsApp;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class MetaCloudDriver implements MessagingDriver
{
    public function __construct(
        private string $accessToken,
        private string $phoneNumberId,
    ) {}

    public function send(string $phone, string $message): array
    {
        $phone = preg_replace('/\D/', '', $phone);
        $url = "https://graph.facebook.com/v19.0/{$this->phoneNumberId}/messages";

        $response = Http::withToken($this->accessToken)->post($url, [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $message],
        ]);

        if ($response->successful()) {
            return ['success' => true, 'error' => null];
        }
        return ['success' => false, 'error' => $response->json('error.message') ?? $response->body()];
    }

    public function label(): string { return 'Meta Cloud API (WhatsApp Business)'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'meta_wa_access_token',   'label' => 'Access Token (Graph API)', 'type' => 'password'],
            ['key' => 'meta_wa_phone_number_id', 'label' => 'Phone Number ID',          'type' => 'text'],
        ];
    }
}
