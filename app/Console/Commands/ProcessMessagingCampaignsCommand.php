<?php

namespace App\Console\Commands;

use App\Jobs\SendMessagingCampaignJob;
use App\Models\MessagingCampaign;
use App\Services\MessagingRecipientsService;
use Illuminate\Console\Command;

class ProcessMessagingCampaignsCommand extends Command
{
    protected $signature = 'messaging-campaign:process';
    protected $description = 'Processa campanhas de WhatsApp/SMS em envio (lotes de 20 por minuto)';

    public function handle(MessagingRecipientsService $service): int
    {
        $campaigns = MessagingCampaign::where('status', 'sending')->get();

        foreach ($campaigns as $campaign) {
            $batch = $service->getNextBatch($campaign, 20);

            if ($batch->isEmpty()) {
                $campaign->update(['status' => 'sent', 'sent_at' => now()]);
                continue;
            }

            foreach ($batch as $recipient) {
                SendMessagingCampaignJob::dispatch(
                    $campaign->id,
                    $recipient['phone'],
                    $recipient['user_id'],
                    $recipient['name'],
                );
            }
        }

        return self::SUCCESS;
    }
}
