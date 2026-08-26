<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NeuroUserScore extends Model
{
    protected $fillable = [
        'tenant_id', 'user_id', 'neuro_indicator_id', 'value', 'notes', 'scored_at',
    ];

    protected $casts = [
        'value'     => 'decimal:2',
        'scored_at' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(NeuroIndicator::class, 'neuro_indicator_id');
    }
}
