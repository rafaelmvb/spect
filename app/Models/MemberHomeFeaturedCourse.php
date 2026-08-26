<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MemberHomeFeaturedCourse extends Model
{
    protected $fillable = ['tenant_id', 'product_id', 'position'];

    public function product(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeForTenant($query, ?int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }
}
