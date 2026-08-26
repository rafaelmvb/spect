<?php

namespace App\Models;

use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;

class MemberAreaPost extends Model
{
    protected $fillable = [
        'tenant_id', 'title', 'category', 'excerpt', 'content',
        'image', 'is_published', 'published_at', 'position',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'published_at' => 'datetime',
    ];

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

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
