<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiInsight extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'type', 'title', 'content',
        'metadata', 'is_read', 'is_dismissed',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'is_read'      => 'boolean',
        'is_dismissed' => 'boolean',
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
