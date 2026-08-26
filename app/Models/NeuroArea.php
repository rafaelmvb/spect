<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NeuroArea extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'description', 'color', 'icon', 'position', 'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'position'  => 'integer',
    ];

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function indicators(): HasMany
    {
        return $this->hasMany(NeuroIndicator::class)->orderBy('position');
    }
}
