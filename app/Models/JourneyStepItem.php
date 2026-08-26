<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JourneyStepItem extends Model
{
    protected $fillable = ['journey_step_id', 'type', 'title', 'data', 'position'];

    protected $casts = ['data' => 'array'];

    public function step(): BelongsTo
    {
        return $this->belongsTo(JourneyStep::class, 'journey_step_id');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(JourneyStepItemResponse::class);
    }
}
