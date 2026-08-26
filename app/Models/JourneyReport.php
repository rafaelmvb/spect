<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JourneyReport extends Model
{
    const STATUS_DRAFT     = 'draft';
    const STATUS_READY     = 'ready';
    const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'tenant_id', 'user_id', 'journey_id', 'product_id',
        'status', 'report_data', 'admin_notes', 'ai_summary',
        'custom_content', 'generated_at', 'published_at',
    ];

    protected $casts = [
        'report_data'  => 'array',
        'generated_at' => 'datetime',
        'published_at' => 'datetime',
    ];

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }
}
