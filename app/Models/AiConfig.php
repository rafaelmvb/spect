<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiConfig extends Model
{
    protected $fillable = [
        'tenant_id', 'context', 'name', 'system_prompt',
        'model', 'temperature', 'max_tokens', 'is_active',
    ];

    protected $casts = [
        'temperature' => 'decimal:2',
        'max_tokens'  => 'integer',
        'is_active'   => 'boolean',
    ];

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
