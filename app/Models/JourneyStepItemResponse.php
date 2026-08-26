<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JourneyStepItemResponse extends Model
{
    protected $fillable = ['journey_step_item_id', 'user_id', 'answer', 'is_correct', 'completed_at'];

    protected $casts = [
        'answer'       => 'array',
        'completed_at' => 'datetime',
        'is_correct'   => 'boolean',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(JourneyStepItem::class, 'journey_step_item_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
