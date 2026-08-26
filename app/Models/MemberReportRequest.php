<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberReportRequest extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'product_id', 'status', 'requested_at', 'approved_at', 'insight_id'];

    protected $casts = ['requested_at' => 'datetime', 'approved_at' => 'datetime'];

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function insight(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\AiInsight::class);
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
