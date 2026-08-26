<?php

namespace App\Messaging\SMS;

use App\Messaging\MessagingDriver;
use Illuminate\Support\Facades\Http;

class AwsSnsDriver implements MessagingDriver
{
    public function __construct(
        private string $accessKey,
        private string $secretKey,
        private string $region,
        private string $senderId = 'Spectra',
    ) {}

    public function send(string $phone, string $message): array
    {
        $phone = '+' . preg_replace('/\D/', '', $phone);
        $endpoint = "https://sns.{$this->region}.amazonaws.com/";
        $datetime = gmdate('Ymd\THis\Z');
        $date = gmdate('Ymd');

        $params = [
            'Action'               => 'Publish',
            'Message'              => $message,
            'PhoneNumber'          => $phone,
            'MessageAttributes.entry.1.Name' => 'AWS.SNS.SMS.SenderID',
            'MessageAttributes.entry.1.Value.DataType' => 'String',
            'MessageAttributes.entry.1.Value.StringValue' => $this->senderId,
            'Version'              => '2010-03-31',
        ];

        // Assinatura AWS Signature V4 simplificada via HTTP
        $response = Http::withHeaders([
            'Content-Type' => 'application/x-www-form-urlencoded',
            'X-Amz-Date'   => $datetime,
        ])->withBasicAuth($this->accessKey, $this->secretKey)
            ->asForm()->post($endpoint, $params);

        if ($response->successful() && str_contains($response->body(), 'MessageId')) {
            return ['success' => true, 'error' => null];
        }
        return ['success' => false, 'error' => $response->body()];
    }

    public function label(): string { return 'AWS SNS SMS'; }

    public static function credentialKeys(): array
    {
        return [
            ['key' => 'aws_sns_access_key', 'label' => 'Access Key ID',            'type' => 'text'],
            ['key' => 'aws_sns_secret_key', 'label' => 'Secret Access Key',         'type' => 'password'],
            ['key' => 'aws_sns_region',     'label' => 'Região (ex: us-east-1)',    'type' => 'text'],
            ['key' => 'aws_sns_sender_id',  'label' => 'Sender ID (nome exibido)',  'type' => 'text'],
        ];
    }
}
