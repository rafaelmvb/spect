<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JourneyStepContent extends Model
{
    protected $fillable = [
        'journey_step_id', 'content_type', 'content_id',
        'content_title', 'position',
    ];

    protected $casts = ['position' => 'integer'];

    public function step(): BelongsTo
    {
        return $this->belongsTo(JourneyStep::class, 'journey_step_id');
    }
}
