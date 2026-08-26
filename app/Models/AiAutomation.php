<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiAutomation extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'description', 'trigger_event',
        'trigger_conditions', 'action_type', 'action_config',
        'is_active', 'executions_count', 'last_executed_at',
    ];

    protected $casts = [
        'trigger_conditions' => 'array',
        'action_config'      => 'array',
        'is_active'          => 'boolean',
        'executions_count'   => 'integer',
        'last_executed_at'   => 'datetime',
    ];

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
