<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInteractionLog extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'context', 'automation_id',
        'input_summary', 'output_summary', 'model',
        'tokens_used', 'latency_ms', 'status', 'error_message',
    ];

    protected $casts = [
        'tokens_used' => 'integer',
        'latency_ms'  => 'integer',
    ];

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
