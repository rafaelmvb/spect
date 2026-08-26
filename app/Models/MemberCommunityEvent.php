<?php

namespace App\Models;

use App\Services\StorageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MemberCommunityEvent extends Model
{
    protected $fillable = [
        'product_id', 'created_by', 'title', 'description',
        'starts_at', 'ends_at', 'location', 'link', 'image', 'is_online', 'rsvp_count',
        'visibility', 'visible_product_ids', 'is_paid', 'price', 'payment_link',
    ];

    protected $casts = [
        'starts_at'           => 'datetime',
        'ends_at'             => 'datetime',
        'is_online'           => 'boolean',
        'is_paid'             => 'boolean',
        'rsvp_count'          => 'integer',
        'price'               => 'decimal:2',
        'visible_product_ids' => 'array',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute(): ?string
    {
        if (empty($this->image)) return null;
        return app(StorageService::class)->url($this->image);
    }

    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
    public function rsvps(): HasMany { return $this->hasMany(MemberCommunityEventRsvp::class, 'event_id'); }
}
