<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ClinicalTest extends Model
{
    protected $fillable = [
        'tenant_id', 'professional_user_id', 'name', 'category', 'description', 'instructions',
        'estimated_minutes', 'is_active', 'position', 'ai_context',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'ai_context' => 'array',
    ];

    public function scopeForTenant(Builder $query, ?int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function questions(): HasMany
    {
        return $this->hasMany(ClinicalTestQuestion::class)->orderBy('position');
    }

    public function scoringRules(): HasMany
    {
        return $this->hasMany(ClinicalTestScoringRule::class)->orderBy('min_score');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(ClinicalTestSession::class);
    }
}
