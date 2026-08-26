<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserJourneyUnlock extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'journey_id',
        'product_id',
        'is_free',
        'ai_insight_id',
    ];

    protected $casts = [
        'is_free' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }
}
