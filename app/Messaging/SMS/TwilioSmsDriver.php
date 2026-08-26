<?php

namespace App\Messaging\SMS;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class TwilioSmsDriver implements MessagingDriver
{
    public function __construct(
        private string $accountSid,
        private string $authToken,
        private string $fromNumber,
    ) {}

    public function send(string $phone, string $message): array
    {
        $phone = $this->e164($phone);
        $url = "https://api.twilio.com/2010-04-01/Accounts/{$this->accountSid}/Messages.json";

        $response = Http::withBasicAuth($this->accountSid, $this->authToken)
            ->asForm()->post($url, ['From' => $this->fromNumber, 'To' => $phone, 'Body' => $message]);

        if ($response->successful()) return ['success' => true, 'error' => null];
        return ['success' => false, 'error' => $response->json('message') ?? $response->body()];
    }

    public function label(): string { return 'Twilio SMS'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'twilio_sms_account_sid', 'label' => 'Account SID',               'type' => 'text'],
            ['key' => 'twilio_sms_auth_token',  'label' => 'Auth Token',                 'type' => 'password'],
            ['key' => 'twilio_sms_from',        'label' => 'Número From (ex: +5511...)', 'type' => 'text'],
        ];
    }

    private function e164(string $phone): string
    {
        $p = preg_replace('/\D/', '', $phone);
        return str_starts_with($p, '+') ? $p : '+' . $p;
    }
}
