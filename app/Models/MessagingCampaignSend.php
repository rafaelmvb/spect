<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MessagingCampaignSend extends Model
{
    protected $fillable = ['messaging_campaign_id', 'user_id', 'phone', 'status', 'error', 'sent_at'];

    protected $casts = ['sent_at' => 'datetime'];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MessagingCampaign::class, 'messaging_campaign_id');
    }
}
