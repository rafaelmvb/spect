<?php

namespace App\Messaging\SMS;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class BrevoSmsDriver implements MessagingDriver
{
    public function __construct(private string $apiKey, private string $sender) {}

    public function send(string $phone, string $message): array
    {
        $phone = '+' . preg_replace('/\D/', '', $phone);

        $response = Http::withHeaders(['api-key' => $this->apiKey])
            ->post('https://api.brevo.com/v3/transactionalSMS/sms', [
                'sender'    => $this->sender,
                'recipient' => $phone,
                'content'   => $message,
                'type'      => 'transactional',
            ]);

        if ($response->successful()) return ['success' => true, 'error' => null];
        return ['success' => false, 'error' => $response->json('message') ?? $response->body()];
    }

    public function label(): string { return 'Brevo (Sendinblue) SMS'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'brevo_sms_api_key', 'label' => 'API Key',  'type' => 'password'],
            ['key' => 'brevo_sms_sender',  'label' => 'Remetente (max 11 chars)', 'type' => 'text'],
        ];
    }
}
