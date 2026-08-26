<?php

namespace App\Messaging\WhatsApp;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class TwilioWhatsAppDriver implements MessagingDriver
{
    public function __construct(
        private string $accountSid,
        private string $authToken,
        private string $fromNumber,
    ) {}

    public function send(string $phone, string $message): array
    {
        $phone = $this->normalizePhone($phone);
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        $response = Http::withBasicAuth($this->accountSid, $this->authToken)
            ->asForm()
            ->post($url, [
                'From' => 'whatsapp:' . $this->fromNumber,
                'To'   => 'whatsapp:' . $phone,
                'Body' => $message,
            ]);

        if ($response->successful()) {
            return ['success' => true, 'error' => null];
        }
        return ['success' => false, 'error' => $response->json('message') ?? $response->body()];
    }

    public function label(): string { return 'Twilio WhatsApp'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'twilio_wa_account_sid', 'label' => 'Account SID', 'type' => 'text'],
            ['key' => 'twilio_wa_auth_token',  'label' => 'Auth Token',   'type' => 'password'],
            ['key' => 'twilio_wa_from',        'label' => 'Número From (ex: +14155238886)', 'type' => 'text'],
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $p = preg_replace('/\D/', '', $phone);
        return str_starts_with($p, '+') ? $p : '+' . $p;
    }
}
