<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MessagingCampaign extends Model
{
    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SENDING   = 'sending';
    public const STATUS_SENT      = 'sent';
    public const STATUS_CANCELLED = 'cancelled';

    public const CHANNEL_WHATSAPP = 'whatsapp';
    public const CHANNEL_SMS      = 'sms';

    protected $fillable = [
        'tenant_id', 'channel', 'provider', 'name', 'message_body',
        'filter_config', 'status', 'total_recipients', 'sent_count', 'failed_count', 'sent_at',
    ];

    protected $casts = [
        'filter_config' => 'array',
        'sent_at' => 'datetime',
        'total_recipients' => 'integer',
        'sent_count' => 'integer',
        'failed_count' => 'integer',
    ];

    public function isDraft(): bool    { return $this->status === self::STATUS_DRAFT; }
    public function isSending(): bool  { return $this->status === self::STATUS_SENDING; }
    public function isSent(): bool     { return $this->status === self::STATUS_SENT; }

    public function sends(): HasMany
    {
        return $this->hasMany(MessagingCampaignSend::class);
    }
}
