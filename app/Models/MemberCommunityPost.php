<?php

namespace App\Models;

use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCommunityPost extends Model
{
    protected $fillable = ['tenant_id', 'member_community_page_id', 'product_id', 'user_id', 'content', 'image', 'video_url', 'is_hidden', 'visible_to_product_ids'];

    protected $casts = [
        'is_hidden'             => 'boolean',
        'visible_to_product_ids' => 'array',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->attributes['image'] ?? null)) {
            return null;
        }
        return app(StorageService::class)->url($this->attributes['image']);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(MemberCommunityPage::class, 'member_community_page_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function likes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberCommunityPostLike::class, 'member_community_post_id');
    }

    public function comments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MemberCommunityPostComment::class, 'member_community_post_id')->with('user:id,name')->latest();
    }
}
