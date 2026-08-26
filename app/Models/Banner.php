<?php

namespace App\Models;

use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'tenant_id', 'title', 'subtitle', 'image',
        'link', 'button_text', 'target', 'is_active', 'position',
    ];

    protected $casts = ['is_active' => 'boolean'];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->attributes['image'] ?? null)) return null;
        return app(StorageService::class)->url($this->attributes['image']);
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
