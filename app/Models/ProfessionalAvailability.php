<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfessionalAvailability extends Model
{
    protected $fillable = [
        'professional_id', 'day_of_week',
        'start_time', 'end_time',
        'lunch_start', 'lunch_end',
        'slot_duration', 'is_active',
    ];

    protected $casts = [
        'day_of_week'    => 'integer',
        'slot_duration'  => 'integer',
        'is_active'      => 'boolean',
    ];

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }
}
