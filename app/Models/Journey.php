<?php

namespace App\Models;

use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Journey extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'slug', 'description',
        'cover', 'is_active', 'position', 'product_ids',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'position'    => 'integer',
        'product_ids' => 'array',
    ];

    protected $appends = ['cover_url'];

    public function getCoverUrlAttribute(): ?string
    {
        if (empty($this->attributes['cover'] ?? null)) return null;
        return app(StorageService::class)->url($this->attributes['cover']);
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(JourneyStep::class)->orderBy('position');
    }
}
