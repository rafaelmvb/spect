<?php

namespace App\Models;

use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberCommunityStory extends Model
{
    protected $fillable = ['product_id', 'created_by', 'content', 'image', 'video_url', 'video_file', 'bg_color', 'expires_at', 'visibility', 'visible_product_ids'];
    protected $casts = ['expires_at' => 'datetime', 'visible_product_ids' => 'array'];
    protected $appends = ['image_url', 'video_file_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) return null;
        return app(StorageService::class)->url($this->image);
    }

    public function getVideoFileUrlAttribute(): ?string
    {
        if (empty($this->video_file)) return null;
        return app(StorageService::class)->url($this->video_file);
    }

    public function likes(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MemberCommunityStoryLike::class, 'story_id');
    }

    public function views(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(\App\Models\MemberCommunityStoryView::class, 'story_id');
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }

    public function isExpired(): bool { return $this->expires_at->isPast(); }
}
