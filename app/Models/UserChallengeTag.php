<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserChallengeTag extends Model
{
    protected $fillable = [
        'user_id', 'tenant_id', 'tag', 'source_type', 'source_id', 'weight', 'weight_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'float',
            'weight_updated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
