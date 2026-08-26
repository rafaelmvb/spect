<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Checkpoint extends Model
{
    protected $fillable = [
        'tenant_id', 'title', 'description',
        'journey_step_id', 'unlock_next_step', 'is_active',
    ];

    protected $casts = [
        'unlock_next_step' => 'boolean',
        'is_active'        => 'boolean',
    ];

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function journeyStep(): BelongsTo
    {
        return $this->belongsTo(JourneyStep::class);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(CheckpointQuestion::class)->orderBy('position');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(CheckpointResponse::class);
    }
}
