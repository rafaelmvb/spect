<?php

namespace App\Jobs;

use App\Messaging\MessagingProviderRegistry;
use App\Models\MessagingCampaign;
use App\Models\MessagingCampaignSend;
use App\Models\Setting;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendMessagingCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $backoff = 60;

    public function __construct(
        public readonly int $campaignId,
        public readonly string $phone,
        public readonly int|null $userId,
        public readonly string $name,
    ) {}

    public function handle(): void
    {
        $campaign = MessagingCampaign::find($this->campaignId);
        if (! $campaign || ! $campaign->isSending()) return;

        $credentials = $this->loadCredentials($campaign->tenant_id, $campaign->provider);
        $driver = MessagingProviderRegistry::make($campaign->provider, $credentials);

        $message = $this->interpolate($campaign->message_body, $this->name, $this->phone);

        try {
            $result = $driver->send($this->phone, $message);
        } catch (\Throwable $e) {
            $result = ['success' => false, 'error' => $e->getMessage()];
        }

        MessagingCampaignSend::updateOrCreate(
            ['messaging_campaign_id' => $this->campaignId, 'phone' => $this->phone],
            [
                'user_id' => $this->userId,
                'status'  => $result['success'] ? 'sent' : 'failed',
                'error'   => $result['error'],
                'sent_at' => now(),
            ]
        );

        if ($result['success']) {
            $campaign->increment('sent_count');
        } else {
            $campaign->increment('failed_count');
            Log::warning("MessagingCampaign #{$this->campaignId} falha [{$this->phone}]: " . $result['error']);
        }
    }

    private function loadCredentials(?int $tenantId, string $provider): array
    {
        $registry = MessagingProviderRegistry::all()[$provider] ?? null;
        if (! $registry) return [];

        $keys = array_column($registry['credential_keys'], 'key');
        $credentials = [];
        foreach ($keys as $key) {
            $value = Setting::get($key, null, $tenantId);
            if ($value) {
                try { $value = decrypt($value); } catch (\Throwable) {}
                $credentials[$key] = $value;
            }
        }
        return $credentials;
    }

    private function interpolate(string $message, string $name, string $phone): string
    {
        return str_replace(['{nome}', '{telefone}'], [$name, $phone], $message);
    }
}
