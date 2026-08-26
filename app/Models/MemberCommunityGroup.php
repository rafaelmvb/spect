<?php

namespace App\Models;

use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberCommunityGroup extends Model
{
    protected $fillable = ['product_id', 'name', 'description', 'image', 'is_private', 'position'];
    protected $casts = ['is_private' => 'boolean'];
    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) return null;
        return app(StorageService::class)->url($this->image);
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function members(): HasMany { return $this->hasMany(MemberCommunityGroupMember::class, 'group_id'); }
}
