<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityBan extends Model
{
    protected $fillable = ['tenant_id', 'user_id', 'product_id', 'reason', 'banned_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function bannedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'banned_by');
    }

    public static function isBanned(int $tenantId, int $userId, string|int|null $productId = null): bool
    {
        return static::where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->where(fn ($q) => $q->whereNull('product_id')->orWhere('product_id', $productId))
            ->exists();
    }
}
