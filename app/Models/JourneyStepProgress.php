<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JourneyStepProgress extends Model
{
    protected $table = 'journey_step_progress';

    protected $fillable = [
        'journey_id', 'journey_step_id', 'user_id', 'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(JourneyStep::class, 'journey_step_id');
    }

    public function journey(): BelongsTo
    {
        return $this->belongsTo(Journey::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
